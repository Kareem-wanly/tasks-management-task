<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Enums\ProjectStatus;
use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BitrixProjectPushTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $colleague;

    private bool $refuse = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actor = User::factory()->create(['bitrix_user_id' => 5]);
        $this->actor->roles()->attach(Role::where('name', 'Administrator')->first());
        $this->actor->storeBitrixToken(new BitrixToken('actor-access', 'actor-refresh', CarbonImmutable::now()->addHour()));
        $this->colleague = User::factory()->create(['bitrix_user_id' => 6, 'name' => 'Colleague']);

        app()->instance(BitrixClient::SERVICE_ACCOUNT, new class implements HasBitrixToken
        {
            public function bitrixToken(): BitrixToken
            {
                return new BitrixToken('service-access', 'service-refresh', CarbonImmutable::now()->addHour());
            }

            public function storeBitrixToken(BitrixToken $token): void {}

            public function bitrixTokenLockKey(): string
            {
                return 'bitrix-token:test';
            }
        });

        Http::fake(function (Request $request) {
            if ($this->refuse) {
                return Http::response(['error' => 'ACCESS_DENIED', 'error_description' => 'Access denied'], 403);
            }

            return Http::response(['result' => str_ends_with($request->url(), 'sonet_group.create.json') ? 40 : true]);
        });
    }

    public function test_new_project_is_created_in_bitrix_by_the_employee(): void
    {
        Sanctum::actingAs($this->actor);

        $this->postJson('/api/projects', ['title' => 'Launch', 'description' => 'Go', 'start_date' => '2026-12-01'])->assertCreated();

        $request = $this->sent('sonet_group.create');
        $this->assertSame('actor-access', $request['auth']);
        $this->assertSame(['Launch', 'Go', 'Y'], [$request['NAME'], $request['DESCRIPTION'], $request['PROJECT']]);
        $this->assertStringStartsWith('2026-12-01', $request['PROJECT_DATE_START']);

        $project = Project::sole();
        $this->assertSame(40, $project->bitrix_group_id);
        $this->assertSame('owner', $project->members()->sole()->pivot->role);
    }

    public function test_update_sends_only_changed_fields(): void
    {
        $project = $this->linkedProject();
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/projects/{$project->id}", ['title' => 'Renamed', 'status' => 'on_hold'])->assertOk();

        $request = $this->sent('sonet_group.update');
        $this->assertSame(['GROUP_ID' => 40, 'NAME' => 'Renamed'], array_diff_key($request->data(), ['auth' => true]));
        // on_hold has no Bitrix equivalent: kept here only.
        $this->assertSame(ProjectStatus::OnHold, $project->fresh()->status);
    }

    public function test_only_local_status_change_sends_nothing(): void
    {
        $project = $this->linkedProject();
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/projects/{$project->id}", ['status' => 'in_progress'])->assertOk();

        Http::assertNothingSent();
    }

    public function test_archiving_closes_the_group_and_unarchiving_reopens_it(): void
    {
        $project = $this->linkedProject();
        Sanctum::actingAs($this->actor);

        $this->postJson("/api/projects/{$project->id}/archive")->assertOk();
        $this->postJson("/api/projects/{$project->id}/archive")->assertOk();

        $closed = Http::recorded()->map(fn (array $pair) => $pair[0]['CLOSED'])->all();
        $this->assertSame(['Y', 'N'], $closed);
    }

    public function test_deleting_archives_the_group_instead_of_deleting_it(): void
    {
        $project = $this->linkedProject();
        Sanctum::actingAs($this->actor);

        $this->deleteJson("/api/projects/{$project->id}")->assertOk();

        $this->assertSame('Y', $this->sent('sonet_group.update')['CLOSED']);
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'sonet_group.delete'));
        $this->assertSoftDeleted($project);
    }

    public function test_members_are_managed_with_the_service_account(): void
    {
        $project = $this->linkedProject();
        Sanctum::actingAs($this->actor);

        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $this->colleague->id, 'role' => 'moderator'])->assertOk();
        $this->deleteJson("/api/projects/{$project->id}/members/{$this->colleague->id}")->assertOk();

        $add = $this->sent('sonet_group.user.add');
        $this->assertSame(['service-access', 40, 6], [$add['auth'], $add['GROUP_ID'], $add['USER_ID']]);
        $this->assertSame('E', $this->sent('sonet_group.user.update')['ROLE']);
        $this->assertSame(6, $this->sent('sonet_group.user.delete')['USER_ID']);
    }

    public function test_plain_member_needs_no_role_change(): void
    {
        $project = $this->linkedProject();
        Sanctum::actingAs($this->actor);

        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $this->colleague->id])->assertOk();

        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'sonet_group.user.update'));
        $this->assertSame('member', $project->members()->sole()->pivot->role);
    }

    public function test_member_who_is_not_in_bitrix_is_refused(): void
    {
        $project = $this->linkedProject();
        $local = User::factory()->create(['name' => 'tester']);
        Sanctum::actingAs($this->actor);

        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $local->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'tester is not a Bitrix24 user, so they cannot be added to this project.');

        $this->assertSame(0, $project->members()->count());
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'sonet_group.user.add'));
    }

    public function test_inactive_user_is_refused_before_bitrix_is_called(): void
    {
        $project = $this->linkedProject();
        $this->colleague->forceFill(['is_active' => false])->save();
        Sanctum::actingAs($this->actor);

        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $this->colleague->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->assertSame(0, $project->members()->count());
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'sonet_group.user.add'));
    }

    public function test_bitrix_refusal_saves_nothing(): void
    {
        $project = $this->linkedProject();
        $this->refuse = true;
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/projects/{$project->id}", ['title' => 'Renamed'])->assertForbidden();

        $this->assertSame('P', $project->fresh()->title);
    }

    public function test_employee_without_bitrix_cannot_create_projects(): void
    {
        $local = User::factory()->create();
        $local->roles()->attach(Role::where('name', 'Administrator')->first());
        Sanctum::actingAs($local);

        $this->postJson('/api/projects', ['title' => 'Launch'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sign in with Bitrix24 to change data that is synced with Bitrix24.');

        $this->assertSame(0, Project::count());
    }

    public function test_local_only_projects_stay_local(): void
    {
        $project = $this->linkedProject(['bitrix_group_id' => null]);
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/projects/{$project->id}", ['title' => 'Renamed'])->assertOk();
        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $this->colleague->id])->assertOk();
        $this->deleteJson("/api/projects/{$project->id}")->assertOk();

        Http::assertNothingSent();
    }

    private function linkedProject(array $attributes = []): Project
    {
        return Project::forceCreate(array_merge([
            'title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 40,
        ], $attributes));
    }

    private function sent(string $method): Request
    {
        $pair = Http::recorded(fn (Request $r): bool => str_ends_with($r->url(), "/{$method}.json"))->first();
        $this->assertNotNull($pair, "{$method} was not called.");

        return $pair[0];
    }
}
