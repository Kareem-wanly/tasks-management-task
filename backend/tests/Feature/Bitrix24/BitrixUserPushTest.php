<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixToken;
use App\Models\BitrixPortal;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BitrixUserPushTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private bool $refuse = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        BitrixPortal::forceCreate([
            'member_id' => 'm',
            'domain' => 'portal.test',
            'application_token' => 't',
            'departments' => [['ID' => '3', 'PARENT' => '1'], ['ID' => '1'], ['ID' => '2', 'PARENT' => '1']],
        ]);

        $this->admin = User::factory()->create(['bitrix_user_id' => 5]);
        $this->admin->roles()->attach(Role::where('name', 'Administrator')->first());
        $this->admin->storeBitrixToken(new BitrixToken('admin-access', 'admin-refresh', CarbonImmutable::now()->addHour()));

        Http::fake(function (Request $request) {
            if ($this->refuse) {
                return Http::response(['error' => 'ERROR_ARGUMENT', 'error_description' => 'A user with this e-mail already exists in Bitrix24.'], 400);
            }

            return Http::response(['result' => str_ends_with($request->url(), 'user.add.json') ? 90 : true]);
        });
    }

    public function test_new_employee_is_invited_to_the_top_department(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/users', ['name' => 'Sara Al Ali', 'email' => 'sara@example.com', 'password' => 'Secret123!'])
            ->assertCreated();

        $request = $this->sent('user.add');
        $this->assertSame('admin-access', $request['auth']);
        $this->assertSame(['sara@example.com', 'Sara', 'Al Ali', [1]], [$request['EMAIL'], $request['NAME'], $request['LAST_NAME'], $request['UF_DEPARTMENT']]);

        $user = User::where('email', 'sara@example.com')->sole();
        $this->assertSame(90, $user->bitrix_user_id);
        $this->assertSame([1], $user->bitrix_department_ids);
        $this->assertTrue($user->hasRole('Member'));
    }

    public function test_bitrix_refusing_the_invitation_creates_nobody(): void
    {
        $this->refuse = true;
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/users', ['name' => 'Sara', 'email' => 'sara@example.com', 'password' => 'Secret123!'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bitrix24 rejected the change: A user with this e-mail already exists in Bitrix24.');

        $this->assertNull(User::where('email', 'sara@example.com')->first());
    }

    public function test_update_sends_name_and_email_changes(): void
    {
        $user = User::factory()->create(['bitrix_user_id' => 6, 'name' => 'Old Name']);
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/users/{$user->id}", ['name' => 'New Name'])->assertOk();

        $request = $this->sent('user.update');
        $this->assertSame([6, 'New', 'Name'], [$request['ID'], $request['NAME'], $request['LAST_NAME']]);
        $this->assertArrayNotHasKey('EMAIL', $request->data());
    }

    public function test_deactivating_and_activating_follow_bitrix(): void
    {
        $user = User::factory()->create(['bitrix_user_id' => 6]);
        $user->createToken('auth_token');
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/v1/users/{$user->id}/active", ['active' => false])->assertOk();
        $this->assertFalse($user->fresh()->is_active);
        // Their open sessions end with it.
        $this->assertSame(0, $user->tokens()->count());

        $this->patchJson("/api/v1/users/{$user->id}/active", ['active' => true])->assertOk();
        $this->assertTrue($user->fresh()->is_active);

        $active = Http::recorded()->map(fn (array $pair) => $pair[0]['ACTIVE'])->all();
        $this->assertSame(['N', 'Y'], $active);
    }

    public function test_deactivated_employee_disappears_from_dropdowns(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        Sanctum::actingAs($this->admin);

        $ids = collect($this->getJson('/api/v1/users/options')->assertOk()->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($user->id));
    }

    public function test_local_only_employee_is_deactivated_here_only(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/v1/users/{$user->id}/active", ['active' => false])->assertOk();

        Http::assertNothingSent();
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_admin_without_bitrix_cannot_invite(): void
    {
        $local = User::factory()->create();
        $local->roles()->attach(Role::where('name', 'Administrator')->first());
        Sanctum::actingAs($local);

        $this->postJson('/api/v1/users', ['name' => 'Sara', 'email' => 'sara@example.com', 'password' => 'Secret123!'])
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertNull(User::where('email', 'sara@example.com')->first());
    }

    private function sent(string $method): Request
    {
        $pair = Http::recorded(fn (Request $r): bool => str_ends_with($r->url(), "/{$method}.json"))->first();
        $this->assertNotNull($pair, "{$method} was not called.");

        return $pair[0];
    }
}
