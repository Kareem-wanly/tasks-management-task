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

    private function fakeBitrix(array $profile = [], string $tokenDomain = 'portal.test'): void
    {
        Http::fake([
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

    private function tokenFrom(TestResponse $response): string
    {
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::FRONTEND.'#token=', $location);

        return urldecode(substr($location, strlen(self::FRONTEND.'#token=')));
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

    public function test_existing_account_with_same_email_is_linked(): void
    {
        $existing = User::factory()->create(['email' => 'sara@portal.test']);
        $this->fakeBitrix();

        $this->tokenFrom($this->oauthCallback());

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(42, $existing->fresh()->bitrix_user_id);
    }

    public function test_returning_employee_is_found_by_bitrix_id(): void
    {
        $user = User::forceCreate(['name' => 'Old', 'email' => 'old@portal.test', 'bitrix_user_id' => 42]);
        $this->fakeBitrix();

        $this->tokenFrom($this->oauthCallback());

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('old@portal.test', $user->fresh()->email);
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

    public function test_app_open_with_a_token_bitrix_rejects_is_refused(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_token'], 401)]);

        $this->post('/auth/bitrix/callback?DOMAIN=portal.test', ['AUTH_ID' => 'forged', 'REFRESH_ID' => 'forged'])
            ->assertRedirect(self::FRONTEND.'#error=bitrix_rejected');

        $this->assertDatabaseCount('users', 0);
    }
}
