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
 * One test per open item from BACKEND_REVIEW round 2 (R1–R18).
 * Each test fails if the bug described in the review is still present.
 */
class Round2ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function userWith(array $permissions, ?string $roleName = null): User
    {
        $role = Role::create([
            'name' => $roleName ?? 'custom_'.uniqid(),
            'display_name' => 'Custom',
            'description' => 'test',
        ]);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function memberUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Member')->first());

        return $user;
    }

    private function project(User $owner, array $members = [], array $attrs = []): Project
    {
        $project = Project::forceCreate(array_merge([
            'title' => 'P '.uniqid(),
            'status' => 'active',
            'owner_id' => $owner->id,
        ], $attrs));
        foreach ($members as $m) {
            $project->members()->attach($m->id, ['role' => 'member']);
        }

        return $project;
    }

    private function task(Project $project, array $attrs = []): Task
    {
        return Task::forceCreate(array_merge([
            'title' => 'T '.uniqid(),
            'status' => 'todo',
            'priority' => 'medium',
            'project_id' => $project->id,
            'created_by' => $project->owner_id,
        ], $attrs));
    }

    public function test_r1_roles_update_cannot_grant_permissions_user_lacks(): void
    {
        $user = $this->userWith(['roles.view', 'roles.update'], 'Limited');
        $role = $user->roles()->first();
        Sanctum::actingAs($user);

        $this->putJson("/api/roles/{$role->id}", [
            'permissions' => Permission::pluck('id')->all(),
        ])->assertStatus(403);
    }

    public function test_r2_owner_can_remove_project_member(): void
    {
        $owner = $this->userWith(['projects.view', 'projects.manage_members']);
        $member = $this->memberUser();
        $project = $this->project($owner, [$member]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/projects/{$project->id}/members/{$member->id}")->assertOk();
        $this->assertFalse($project->members()->where('users.id', $member->id)->exists());
    }

    public function test_r4_cannot_assign_task_to_non_member_on_create(): void
    {
        $owner = $this->userWith(['projects.view', 'tasks.view', 'tasks.create']);
        $outsider = User::factory()->create();
        $project = $this->project($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'x', 'assigned_to' => $outsider->id,
        ])->assertStatus(422);
    }

    public function test_r4_cannot_assign_task_to_non_member_on_update(): void
    {
        $owner = $this->userWith(['projects.view', 'tasks.view', 'tasks.update']);
        $outsider = User::factory()->create();
        $project = $this->project($owner);
        $task = $this->task($project);
        Sanctum::actingAs($owner);

        $this->putJson("/api/tasks/{$task->id}", [
            'assigned_to' => $outsider->id,
        ])->assertStatus(422);
    }

    public function test_r5_view_only_member_cannot_change_status(): void
    {
        $owner = $this->memberUser();
        $viewer = $this->userWith(['projects.view', 'tasks.view']);
        $project = $this->project($owner, [$viewer]);
        $task = $this->task($project, ['assigned_to' => $viewer->id]);
        Sanctum::actingAs($viewer);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'review'])->assertStatus(403);
    }

    public function test_r6_task_of_deleted_project_does_not_500(): void
    {
        $owner = $this->memberUser();
        $project = $this->project($owner);
        $task = $this->task($project);
        Sanctum::actingAs($owner);

        $project->delete();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertNotEquals(500, $this->getJson("/api/tasks/{$task->id}")->status());
    }

    public function test_r7_roles_assign_user_cannot_give_self_a_role(): void
    {
        $user = $this->userWith(['roles.assign', 'users.view']);
        $pm = Role::where('name', 'Project Manager')->first();
        Sanctum::actingAs($user);

        $this->putJson("/api/users/{$user->id}/roles", ['roles' => [$pm->id]])->assertStatus(403);
    }

    public function test_r8_saving_user_after_permission_lookup_works(): void
    {
        $user = $this->memberUser();
        $user->permissionNames();
        $user->hasRole('Member');
        $user->name = 'Changed';

        $this->assertTrue($user->save());
        $this->assertArrayNotHasKey('permissionCache', $user->toArray());
    }

    public function test_r9_me_returns_effective_permissions_for_the_frontend(): void
    {
        $user = $this->memberUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.effective_permissions', fn ($p) => in_array('projects.view', (array) $p));
    }

    public function test_r9_project_members_do_not_expose_permissions(): void
    {
        $owner = $this->memberUser();
        $member = $this->memberUser();
        $project = $this->project($owner, [$member]);
        Sanctum::actingAs($owner);

        $json = json_encode($this->getJson("/api/projects/{$project->id}")->assertOk()->json());
        $this->assertStringNotContainsString('effective_permissions', $json);
    }

    public function test_r10_due_date_from_filter_works(): void
    {
        $owner = $this->memberUser();
        $project = $this->project($owner);
        $this->task($project, ['title' => 'early', 'due_date' => '2026-01-01']);
        $this->task($project, ['title' => 'late', 'due_date' => '2026-12-01']);
        Sanctum::actingAs($owner);

        $titles = collect($this->getJson('/api/tasks?due_date_from=2026-06-01')->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('late'));
        $this->assertFalse($titles->contains('early'));
    }

    public function test_r11_deleting_user_who_owns_project_returns_409(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $owner = $this->memberUser();
        $this->project($owner);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$owner->id}")->assertStatus(409);
    }

    public function test_r12_user_can_read_own_roles(): void
    {
        $user = $this->memberUser();
        Sanctum::actingAs($user);

        $this->getJson("/api/users/{$user->id}/roles")->assertOk();
    }

    public function test_r13_member_and_pm_can_open_activities(): void
    {
        foreach (['Member', 'Project Manager'] as $roleName) {
            $user = User::factory()->create();
            $user->roles()->attach(Role::where('name', $roleName)->first());
            Sanctum::actingAs($user);
            $this->getJson('/api/activities')->assertOk();
        }
    }

    public function test_r14_tasks_create_permission_is_enough_to_create_tasks(): void
    {
        $user = $this->userWith(['projects.view', 'tasks.view', 'tasks.create']);
        $project = $this->project($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/tasks", ['title' => 'ok'])->assertStatus(201);
    }

    public function test_r14_projects_update_alone_is_not_enough_to_create_tasks(): void
    {
        $user = $this->userWith(['projects.view', 'projects.update']);
        $project = $this->project($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/tasks", ['title' => 'no'])->assertStatus(403);
    }

    public function test_r15_task_created_as_completed_gets_completed_at(): void
    {
        $owner = $this->userWith(['projects.view', 'tasks.view', 'tasks.create']);
        $project = $this->project($owner);
        Sanctum::actingAs($owner);

        $id = $this->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'done', 'status' => 'completed',
        ])->assertStatus(201)->json('data.id');

        $this->assertNotNull(Task::find($id)->completed_at);
    }

    public function test_r18_project_due_date_cannot_move_before_open_task(): void
    {
        $owner = $this->userWith(['projects.view', 'projects.update']);
        $project = $this->project($owner, [], ['due_date' => '2026-12-31']);
        $this->task($project, ['due_date' => '2026-12-01']);
        Sanctum::actingAs($owner);

        $this->putJson("/api/projects/{$project->id}", ['due_date' => '2026-11-01'])->assertStatus(422);
    }
}
