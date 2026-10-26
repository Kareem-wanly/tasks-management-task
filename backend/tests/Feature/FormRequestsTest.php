<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    public function test_comment_outsider_gets_403_not_422(): void
    {
        $task = $this->task($this->project($this->member()));
        Sanctum::actingAs($this->member());

        $this->postJson("/api/tasks/{$task->id}/comments", ['body' => ''])->assertStatus(403);
    }

    public function test_comment_body_is_validated_for_members(): void
    {
        $owner = $this->member();
        $task = $this->task($this->project($owner));
        Sanctum::actingAs($owner);

        $this->postJson("/api/tasks/{$task->id}/comments", ['body' => str_repeat('a', 5001)])->assertStatus(422);
        $this->postJson("/api/tasks/{$task->id}/comments", ['body' => 'ok'])->assertStatus(201);
    }

    public function test_update_status_authorizes_before_validating(): void
    {
        $owner = $this->member();
        $viewer = $this->userWith(['projects.view', 'tasks.view']);
        $task = $this->task($this->project($owner, [$viewer]));

        Sanctum::actingAs($viewer);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'nonsense'])->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'nonsense'])->assertStatus(422);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'review'])->assertOk();
    }

    public function test_add_member_validates_and_authorizes(): void
    {
        $owner = $this->userWith(['projects.view', 'projects.manage_members']);
        $project = $this->project($owner);
        $newcomer = $this->member();

        Sanctum::actingAs($this->member());
        $this->postJson("/api/projects/{$project->id}/members", [])->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->postJson("/api/projects/{$project->id}/members", [])->assertStatus(422);
        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $newcomer->id])->assertSuccessful();
        $this->assertSame('member', $project->members()->where('users.id', $newcomer->id)->first()->pivot->role);
        $this->postJson("/api/projects/{$project->id}/members", ['user_id' => $newcomer->id])->assertStatus(422);
    }

    public function test_store_user_authorizes_then_validates_and_defaults_to_member_role(): void
    {
        Sanctum::actingAs($this->member());
        $this->postJson('/api/users', [])->assertStatus(403);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/users', ['name' => 'X', 'email' => 'x@example.com'])->assertStatus(422);

        $id = $this->postJson('/api/users', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'Secret123!',
        ])->assertStatus(201)->json('data.id');

        $this->assertTrue(User::find($id)->hasRole('Member'));
    }

    public function test_update_user_allows_keeping_own_email_but_not_taking_anothers(): void
    {
        $target = $this->member();
        $other = $this->member();
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/users/{$target->id}", ['email' => $target->email])->assertOk();
        $this->putJson("/api/users/{$target->id}", ['email' => $other->email])->assertStatus(422);
    }

    public function test_sync_roles_keeps_self_modification_message_and_validates(): void
    {
        $user = $this->userWith(['roles.assign', 'users.view']);
        Sanctum::actingAs($user);
        $this->putJson("/api/users/{$user->id}/roles", ['roles' => []])
            ->assertStatus(403)
            ->assertJsonPath('message', 'You cannot modify your own roles.');

        $target = $this->member();
        Sanctum::actingAs($this->admin());
        $this->putJson("/api/users/{$target->id}/roles", [])->assertStatus(422);
    }
}
