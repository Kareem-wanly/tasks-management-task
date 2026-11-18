<?php

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BitrixUserColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function bitrixEmployee(): User
    {
        return User::forceCreate([
            'name' => 'Bitrix Employee',
            'email' => 'employee@portal.test',
            'password' => null,
            'bitrix_user_id' => 42,
        ]);
    }

    public function test_employee_from_bitrix_can_exist_without_a_password(): void
    {
        $user = $this->bitrixEmployee();

        $this->assertNull($user->fresh()->password);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_passwordless_employee_cannot_log_in_with_a_password(): void
    {
        $this->bitrixEmployee();

        $this->postJson('/api/login', ['email' => 'employee@portal.test', 'password' => 'anything'])
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid login credentials']);
    }

    public function test_bitrix_user_id_is_unique(): void
    {
        $this->bitrixEmployee();

        $this->expectException(UniqueConstraintViolationException::class);

        User::forceCreate(['name' => 'Copy', 'email' => 'copy@portal.test', 'bitrix_user_id' => 42]);
    }

    public function test_user_token_is_encrypted_hidden_and_refreshed_for_calls(): void
    {
        config([
            'bitrix.portal' => 'portal.test',
            'bitrix.client_id' => 'app-id',
            'bitrix.client_secret' => 'app-secret',
            'bitrix.oauth_server' => 'https://oauth.bitrix.info',
        ]);
        Http::fake([
            'https://oauth.bitrix.info/*' => Http::response([
                'access_token' => 'user-new-access',
                'refresh_token' => 'user-new-refresh',
                'expires_in' => 3600,
            ]),
            'https://portal.test/rest/*' => Http::response(['result' => ['ID' => '42']]),
        ]);

        $user = $this->bitrixEmployee();
        $user->storeBitrixToken(new BitrixToken('user-access', 'user-refresh', CarbonImmutable::now()->addSeconds(10)));

        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotSame('user-access', $row->bitrix_access_token);
        $this->assertArrayNotHasKey('bitrix_access_token', $user->toArray());
        $this->assertArrayNotHasKey('bitrix_refresh_token', $user->toArray());

        app(BitrixClient::class)->callAsUser($user, 'user.current');

        $this->assertSame('user-new-access', $user->fresh()->bitrixToken()->accessToken);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/rest/user.current.json') && $r['auth'] === 'user-new-access');
    }
}
