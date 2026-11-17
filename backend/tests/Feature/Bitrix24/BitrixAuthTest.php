<?php

namespace Tests\Feature\Bitrix24;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BitrixAuthTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND = 'http://localhost:5173/auth/bitrix/callback';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'bitrix.portal' => 'portal.test',
            'bitrix.client_id' => 'app-id',
            'bitrix.client_secret' => 'app-secret',
            'bitrix.oauth_server' => 'https://oauth.bitrix.info',
            // Same host the test requests use, so redirect() doesn't hop.
            'bitrix.public_url' => config('app.url'),
            'bitrix.frontend_callback_url' => self::FRONTEND,
        ]);
        Role::create(['name' => 'Member', 'display_name' => 'Team Member', 'description' => 'x']);
    }

    private function fakeBitrix(array $profile = [], string $tokenDomain = 'portal.test', string $appCode = 'app-id'): void
    {
        Http::fake([
            // A local app's CODE is its client_id.
            'https://portal.test/rest/app.info.json' => Http::response(['result' => ['ID' => 40, 'CODE' => $appCode]]),
            'https://oauth.bitrix.info/*' => Http::response([
                'access_token' => 'user-access',
                'refresh_token' => 'user-refresh',
                'expires_in' => 3600,
                'client_endpoint' => "https://{$tokenDomain}/rest/",
                'member_id' => 'member-1',
            ]),
            'https://portal.test/rest/user.current.json' => Http::response(['result' => array_merge([
                'ID' => '42',
                'NAME' => 'Sara',
                'LAST_NAME' => 'Ali',
                'EMAIL' => 'Sara@Portal.test',
                'PERSONAL_PHOTO' => 'https://portal.test/photo.jpg',
                'WORK_POSITION' => 'Engineer',
            ], $profile)]),
        ]);
    }

    private function oauthCallback(array $query = []): TestResponse
    {
        return $this->withSession(['bitrix_oauth_state' => 'good-state'])
            ->get('/auth/bitrix/callback?'.http_build_query(array_merge([
                'code' => 'auth-code',
                'state' => 'good-state',
                'domain' => 'portal.test',
                'member_id' => 'member-1',
            ], $query)));
    }

    /** The one-time code the callback hands the frontend. */
    private function codeFrom(TestResponse $response): string
    {
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(self::FRONTEND.'#code=', $location);

        return substr($location, strlen(self::FRONTEND.'#code='));
    }

    /** Signs in the way the frontend does: trades the code for an API token. */
    private function tokenFrom(TestResponse $response): string
    {
        return $this->postJson('/api/auth/bitrix/exchange', ['code' => $this->codeFrom($response)])
            ->assertOk()
            ->json('token');
    }

    public function test_redirect_sends_the_user_to_bitrix_with_a_state(): void
    {
        $response = $this->get('/auth/bitrix/redirect');

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame(40, strlen($query['state']));
        $response->assertRedirect('https://portal.test/oauth/authorize/?client_id=app-id&state='.$query['state']);
        $response->assertSessionHas('bitrix_oauth_state', $query['state']);
    }

    public function test_redirect_moves_to_the_public_host_first(): void
    {
        config(['bitrix.public_url' => 'https://public.test']);

        $this->get('/auth/bitrix/redirect')->assertRedirect('https://public.test/auth/bitrix/redirect');
        $this->assertNull(session('bitrix_oauth_state'));
    }

    public function test_redirect_does_not_loop_when_proxy_downgrades_to_http(): void
    {
        config(['bitrix.public_url' => 'https://public.test']);

        $this->get('http://public.test/auth/bitrix/redirect')
            ->assertRedirectContains('https://portal.test/oauth/authorize/');
    }

    public function test_wrong_state_is_rejected(): void
    {
        Http::fake();

        $this->oauthCallback(['state' => 'forged'])->assertRedirect(self::FRONTEND.'#error=invalid_state');

        Http::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_another_portal_is_rejected(): void
    {
        Http::fake();

        $this->oauthCallback(['domain' => 'evil.test'])->assertRedirect(self::FRONTEND.'#error=unknown_portal');

        Http::assertNothingSent();
    }

    public function test_token_issued_for_another_portal_is_rejected(): void
    {
        $this->fakeBitrix(tokenDomain: 'evil.test');

        $this->oauthCallback()->assertRedirect(self::FRONTEND.'#error=unknown_portal');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_new_employee_is_created_as_member(): void
    {
        $this->fakeBitrix();

        $token = $this->tokenFrom($this->oauthCallback());

        $user = User::sole();
        $this->assertSame(42, $user->bitrix_user_id);
        $this->assertSame('Sara Ali', $user->name);
        $this->assertSame('sara@portal.test', $user->email);
        $this->assertNull($user->password);
        $this->assertSame('Engineer', $user->position);
        $this->assertSame('https://portal.test/photo.jpg', $user->avatar_url);
        $this->assertTrue($user->hasRole('Member'));

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.position', 'Engineer')
            ->assertJsonPath('user.avatar_url', 'https://portal.test/photo.jpg');
    }

    /**
     * Review A3: an employee who puts the administrator's email on their Bitrix
     * profile must not get the administrator's account.
     */
    public function test_sign_in_never_takes_over_an_account_by_email(): void
    {
        $admin = User::factory()->create(['email' => 'sara@portal.test']);
        $admin->roles()->attach(Role::create(['name' => 'Administrator', 'display_name' => 'Admin', 'description' => 'x']));
        $this->fakeBitrix();

        $this->oauthCallback()->assertRedirect(self::FRONTEND.'#error=account_not_linked');

        $this->assertDatabaseCount('users', 1);
        $this->assertNull($admin->fresh()->bitrix_user_id);
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_returning_employee_is_found_by_bitrix_id(): void
    {
        $user = User::forceCreate(['name' => 'Old', 'email' => 'old@portal.test', 'bitrix_user_id' => 42]);
        $this->fakeBitrix();

        $this->tokenFrom($this->oauthCallback());

        $this->assertDatabaseCount('users', 1);
        // Bitrix wins: name and email follow the Bitrix profile.
        $this->assertSame('sara@portal.test', $user->fresh()->email);
        $this->assertSame('Sara Ali', $user->fresh()->name);
    }

    public function test_tokens_are_saved_encrypted(): void
    {
        $this->fakeBitrix();

        $this->tokenFrom($this->oauthCallback());

        $user = User::sole();
        $this->assertSame('user-access', $user->bitrixToken()->accessToken);
        $this->assertSame('user-refresh', $user->bitrixToken()->refreshToken);
        $this->assertNotSame('user-access', DB::table('users')->value('bitrix_access_token'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth/token') && $r['code'] === 'auth-code' && $r['grant_type'] === 'authorization_code');
    }

    public function test_inactive_employee_cannot_sign_in(): void
    {
        User::forceCreate(['name' => 'Gone', 'email' => 'gone@portal.test', 'bitrix_user_id' => 42, 'is_active' => false]);
        $this->fakeBitrix();

        $this->oauthCallback()->assertRedirect(self::FRONTEND.'#error=inactive');
    }

    public function test_state_cannot_be_reused(): void
    {
        $this->fakeBitrix();
        $this->tokenFrom($this->oauthCallback());

        $this->get('/auth/bitrix/callback?code=again&state=good-state&domain=portal.test')
            ->assertRedirect(self::FRONTEND.'#error=invalid_state');
    }

    public function test_opening_the_app_inside_bitrix_signs_the_user_in(): void
    {
        $this->fakeBitrix();

        $response = $this->post('/auth/bitrix/callback?DOMAIN=portal.test&PROTOCOL=1&LANG=en&APP_SID=s', [
            'AUTH_ID' => 'frame-access',
            'AUTH_EXPIRES' => '3600',
            'REFRESH_ID' => 'frame-refresh',
            'member_id' => 'member-1',
            'PLACEMENT' => 'DEFAULT',
        ]);

        $this->tokenFrom($response);
        $this->assertSame('frame-access', User::sole()->bitrixToken()->accessToken);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'user.current') && $r['auth'] === 'frame-access');
    }

    /** Review B1: no API token ever travels in the URL; the code works once. */
    public function test_the_callback_hands_over_a_one_time_code_not_a_token(): void
    {
        $this->fakeBitrix();
        $code = $this->codeFrom($this->oauthCallback());

        $this->assertSame(0, User::sole()->tokens()->count());
        $this->postJson('/api/auth/bitrix/exchange', ['code' => $code])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/auth/bitrix/exchange', ['code' => $code])->assertStatus(422);
        $this->assertSame(1, User::sole()->tokens()->count());
    }

    public function test_the_code_expires_after_a_minute(): void
    {
        $this->fakeBitrix();
        $code = $this->codeFrom($this->oauthCallback());

        $this->travel(61)->seconds();

        $this->postJson('/api/auth/bitrix/exchange', ['code' => $code])->assertStatus(422);
    }

    /** Review B1: a valid token of another app on the portal does not sign anyone in. */
    public function test_app_open_with_a_token_of_another_app_is_refused(): void
    {
        $this->fakeBitrix(appCode: 'someone-elses-app');

        $this->post('/auth/bitrix/callback?DOMAIN=portal.test', ['AUTH_ID' => 'other-app-access', 'REFRESH_ID' => 'other-app-refresh'])
            ->assertRedirect(self::FRONTEND.'#error=bitrix_rejected');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_app_open_with_a_token_bitrix_rejects_is_refused(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_token'], 401)]);

        $this->post('/auth/bitrix/callback?DOMAIN=portal.test', ['AUTH_ID' => 'forged', 'REFRESH_ID' => 'forged'])
            ->assertRedirect(self::FRONTEND.'#error=bitrix_rejected');

        $this->assertDatabaseCount('users', 0);
    }
}
