<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tasks may have no project (Bitrix personal tasks, GROUP_ID = 0). Only
 * their creator and responsible person see them, plus administrators.
 */
class TasksWithoutProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $assignee;

    private User $outsider;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $permissions = ['projects.view', 'tasks.view', 'tasks.update', 'tasks.delete', 'comments.view', 'comments.create'];
        $role = Role::create(['name' => 'tester', 'display_name' => 'Tester', 'description' => 'x']);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        [$this->creator, $this->assignee, $this->outsider] = User::factory()->count(3)->create()->all();
        foreach ([$this->creator, $this->assignee, $this->outsider] as $user) {
            $user->roles()->attach($role);
        }

        $this->task = Task::forceCreate([
            'title' => 'Personal',
            'status' => 'todo',
            'priority' => 'medium',
            'project_id' => null,
            'created_by' => $this->creator->id,
            'assigned_to' => $this->assignee->id,
        ]);
    }

    public function test_creator_and_assignee_see_it_in_the_list(): void
    {
        foreach ([$this->creator, $this->assignee] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('data.0.id', $this->task->id);
        }
    }

    public function test_others_do_not_see_it(): void
    {
        Sanctum::actingAs($this->outsider);

        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/tasks/{$this->task->id}")->assertForbidden();
        $this->deleteJson("/api/v1/tasks/{$this->task->id}")->assertForbidden();
    }

    public function test_administrator_sees_it(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'Administrator')->first());
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/tasks/{$this->task->id}")->assertOk()->assertJsonPath('data.project', null);
    }

    public function test_creator_can_open_update_and_delete_it(): void
    {
        Sanctum::actingAs($this->creator);

        $this->getJson("/api/v1/tasks/{$this->task->id}")->assertOk();
        $this->putJson("/api/v1/tasks/{$this->task->id}", ['title' => 'Renamed'])->assertOk();
        $this->assertSame('Renamed', $this->task->fresh()->title);
        $this->deleteJson("/api/v1/tasks/{$this->task->id}")->assertSuccessful();
    }

    public function test_assignee_can_chat_and_others_cannot(): void
    {
        Sanctum::actingAs($this->assignee);
        $this->postJson("/api/v1/tasks/{$this->task->id}/chat", ['text' => 'On it'])->assertOk();
        $this->getJson("/api/v1/tasks/{$this->task->id}/chat")->assertOk()->assertJsonPath('data.0.text', 'On it');

        Sanctum::actingAs($this->outsider);
        $this->getJson("/api/v1/tasks/{$this->task->id}/chat")->assertForbidden();
    }

    /** SEC-2: with no project there is no membership check, so the user itself must be active. */
    public function test_a_new_task_without_a_project_is_not_assigned_to_a_deactivated_user(): void
    {
        $deactivated = User::factory()->create(['is_active' => false]);
        Sanctum::actingAs($this->administrator());

        $this->postJson('/api/v1/tasks', ['title' => 'Personal', 'assigned_to' => $deactivated->id])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['assigned_to']);
        $this->assertDatabaseMissing('tasks', ['assigned_to' => $deactivated->id]);
    }

    public function test_a_task_without_a_project_is_not_reassigned_to_a_deactivated_user(): void
    {
        $deactivated = User::factory()->create(['is_active' => false]);
        Sanctum::actingAs($this->administrator());

        $this->putJson("/api/v1/tasks/{$this->task->id}", ['assigned_to' => $deactivated->id])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['assigned_to']);
        $this->assertSame($this->assignee->id, $this->task->fresh()->assigned_to);
    }

    public function test_the_assignee_sees_a_project_task_outside_its_project_as_in_bitrix(): void
    {
        $project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->creator->id]);
        $projectTask = Task::forceCreate([
            'title' => 'In project',
            'status' => 'todo',
            'priority' => 'medium',
            'project_id' => $project->id,
            'created_by' => $this->creator->id,
            'assigned_to' => $this->outsider->id,
        ]);
        $otherTask = Task::forceCreate([...$projectTask->only('status', 'priority', 'project_id', 'created_by'), 'title' => 'Not theirs']);
        Sanctum::actingAs($this->outsider);

        $this->getJson("/api/v1/tasks/{$projectTask->id}")->assertOk();
        $this->getJson('/api/v1/tasks')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $projectTask->id);
        // The rest of the project stays hidden: membership still decides that.
        $this->getJson("/api/v1/tasks/{$otherTask->id}")->assertForbidden();
    }

    /** May create and assign any task, so a refusal can only come from validation. */
    private function administrator(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'Administrator')->first());

        return $admin;
    }
}
