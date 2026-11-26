<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Integrations\Bitrix24\BitrixToken;
use App\Models\BitrixPortal;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the FormRequest classes that replaced inline validation (review item F10).
 * Authorization must run before validation, so an unauthorized caller gets 403, never 422.
 */
class FormRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_chat_outsider_gets_403_not_422(): void
    {
        $task = $this->task($this->project($this->member()));
        Sanctum::actingAs($this->member());

        $this->postJson("/api/v1/tasks/{$task->id}/chat", ['text' => ''])->assertStatus(403);
    }

    public function test_chat_message_is_validated_for_members(): void
    {
        $owner = $this->member();
        $task = $this->task($this->project($owner));
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/tasks/{$task->id}/chat", ['text' => str_repeat('a', 5001)])->assertStatus(422);
        $this->postJson("/api/v1/tasks/{$task->id}/chat", ['text' => 'ok'])->assertOk()->assertJsonPath('data.0.text', 'ok');
    }

    public function test_update_status_authorizes_before_validating(): void
    {
        $owner = $this->member();
        $viewer = $this->userWith(['projects.view', 'tasks.view']);
        $task = $this->task($this->project($owner, [$viewer]));

        Sanctum::actingAs($viewer);
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'nonsense'])->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'nonsense'])->assertStatus(422);
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'review'])->assertOk();
    }

    public function test_add_member_validates_and_authorizes(): void
    {
        $owner = $this->userWith(['projects.view', 'projects.manage_members']);
        $project = $this->project($owner);
        $newcomer = $this->member();

        Sanctum::actingAs($this->member());
        $this->postJson("/api/v1/projects/{$project->id}/members", [])->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/projects/{$project->id}/members", [])->assertStatus(422);
        $this->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => $newcomer->id])->assertSuccessful();
        $this->assertSame('member', $project->members()->where('users.id', $newcomer->id)->first()->pivot->role);
        $this->postJson("/api/v1/projects/{$project->id}/members", ['user_id' => $newcomer->id])->assertStatus(422);
    }

    public function test_store_user_authorizes_then_validates_and_defaults_to_member_role(): void
    {
        Sanctum::actingAs($this->member());
        $this->postJson('/api/v1/users', [])->assertStatus(403);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@example.com'])->assertStatus(422);

        // A valid request invites the employee in Bitrix24, as a linked admin.
        config(['bitrix.portal' => 'portal.test']);
        BitrixPortal::forceCreate(['member_id' => 'm', 'domain' => 'portal.test', 'application_token' => 't', 'departments' => [['ID' => '1']]]);
        $admin = $this->admin();
        $admin->forceFill(['bitrix_user_id' => 1])->save();
        $this->giveBitrixToken($admin, new BitrixToken('a', 'r', CarbonImmutable::now()->addHour()));
        Sanctum::actingAs($admin);
        Http::fake(['*' => Http::response(['result' => 77])]);

        $id = $this->postJson('/api/v1/users', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'Secret123!',
        ])->assertStatus(201)->json('data.id');

        $this->assertTrue(User::find($id)->hasRole('Member'));
    }

    public function test_update_user_allows_keeping_own_email_but_not_taking_anothers(): void
    {
        $target = $this->member();
        $other = $this->member();
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/v1/users/{$target->id}", ['email' => $target->email])->assertOk();
        $this->putJson("/api/v1/users/{$target->id}", ['email' => $other->email])->assertStatus(422);
    }

    public function test_sync_roles_keeps_self_modification_message_and_validates(): void
    {
        $user = $this->userWith(['roles.assign', 'users.view']);
        Sanctum::actingAs($user);
        $this->putJson("/api/v1/users/{$user->id}/roles", ['roles' => []])
            ->assertStatus(403)
            ->assertJsonPath('message', 'You cannot modify your own roles.');

        $target = $this->member();
        Sanctum::actingAs($this->admin());
        $this->putJson("/api/v1/users/{$target->id}/roles", [])->assertStatus(422);
    }

    public static function outOfRangePageSizes(): array
    {
        $endpoints = ['/api/v1/tasks', '/api/v1/projects', '/api/v1/users'];
        $sizes = ['zero' => '0', 'negative' => '-1', 'over the limit' => '101', 'not a number' => 'abc'];
        $cases = [];
        foreach ($endpoints as $endpoint) {
            foreach ($sizes as $label => $size) {
                $cases["{$endpoint} {$label}"] = [$endpoint, $size];
            }
        }

        return $cases;
    }

    #[DataProvider('outOfRangePageSizes')]
    public function test_page_size_outside_1_to_100_is_rejected(string $endpoint, string $perPage): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson("{$endpoint}?per_page={$perPage}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_page_size_at_the_limits_is_accepted(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/tasks?per_page=1')->assertOk()->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/v1/tasks?per_page=100')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_task_list_filters_are_validated(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/tasks?assigned_to=abc&project_id=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to', 'project_id']);
        $this->getJson('/api/v1/tasks?status[]=pending')->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_empty_task_list_filters_filter_nothing(): void
    {
        $admin = $this->admin();
        $this->task($this->project($admin));
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/tasks?assigned_to=&project_id=&status=&search=')
            ->assertOk()
            ->assertJsonPath('meta.total', Task::count());
    }

    public function test_project_status_is_still_a_plain_string_in_the_api(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/projects/{$project->id}")->assertOk()->assertJsonPath('data.status', 'active');
        $this->postJson("/api/v1/projects/{$project->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('message', 'Project status changed to archived successfully');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->first();
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Member')->first());

        return $user;
    }

    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'c_'.uniqid(), 'display_name' => 'C', 'description' => 'x']);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function project(User $owner, array $members = []): Project
    {
        $project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $owner->id]);
        foreach ($members as $m) {
            $project->members()->attach($m->id, ['role' => 'member']);
        }

        return $project;
    }

    private function task(Project $project): Task
    {
        return Task::forceCreate([
            'title' => 'T', 'status' => 'todo', 'priority' => 'low',
            'project_id' => $project->id, 'created_by' => $project->owner_id,
        ]);
    }
}
