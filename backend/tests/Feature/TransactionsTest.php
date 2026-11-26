<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use App\Repositories\Contracts\TaskChecklistRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Review B3: a write made of several steps either happens whole or not at
 * all. Each test makes a later step fail and checks the earlier one rolled back.
 */
class TransactionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('name', 'Administrator')->first());
        // A local-only project: its tasks change here only, no Bitrix calls.
        $project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->admin->id]);
        $this->task = Task::forceCreate([
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'project_id' => $project->id,
            'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id,
        ]);
        Sanctum::actingAs($this->admin);
    }

    public function test_a_role_whose_permissions_are_refused_is_not_created(): void
    {
        $limited = User::factory()->create();
        $role = Role::create(['name' => 'Role maker', 'display_name' => 'Role maker', 'description' => 'x']);
        $role->permissions()->sync(Permission::whereIn('name', ['roles.view', 'roles.create'])->pluck('id'));
        $limited->roles()->attach($role);
        Sanctum::actingAs($limited);

        $this->postJson('/api/roles', ['name' => 'Sneaky', 'permissions' => Permission::pluck('id')->all()])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Sneaky']);
    }

    public function test_members_change_together_or_not_at_all(): void
    {
        $colleague = User::factory()->create();
        $this->failing(TaskRepositoryInterface::class, 'syncAuditors');

        $this->withoutExceptionHandling();
        $this->assertThrows(fn () => $this->putJson("/api/tasks/{$this->task->id}/members", ['accomplice_ids' => [$colleague->id], 'auditor_ids' => [$colleague->id]]), RuntimeException::class, 'Simulated failure.');

        $this->assertSame(0, $this->task->accomplices()->count());
    }

    public function test_a_local_delegation_rolls_back_when_a_step_fails(): void
    {
        $colleague = User::factory()->create();
        $this->failing(TaskRepositoryInterface::class, 'addAuditor');

        $this->withoutExceptionHandling();
        $this->assertThrows(fn () => $this->postJson("/api/tasks/{$this->task->id}/delegate", ['user_id' => $colleague->id]), RuntimeException::class, 'Simulated failure.');

        $this->assertSame($this->admin->id, $this->task->fresh()->assigned_to);
    }

    public function test_a_checklist_reorder_rolls_back_when_it_fails_midway(): void
    {
        $list = TaskChecklistItem::create(['task_id' => $this->task->id, 'parent_id' => null, 'title' => 'L', 'sort_index' => 1]);
        $first = TaskChecklistItem::create(['task_id' => $this->task->id, 'parent_id' => $list->id, 'title' => 'A', 'sort_index' => 1]);
        $second = TaskChecklistItem::create(['task_id' => $this->task->id, 'parent_id' => $list->id, 'title' => 'B', 'sort_index' => 2]);
        // The first update lands, then the reorder fails.
        $this->failing(TaskChecklistRepositoryInterface::class, 'reorder', fn (array $items) => $items[0]->update(['sort_index' => 99]));

        $this->withoutExceptionHandling();
        $this->assertThrows(fn () => $this->postJson("/api/checklist-items/{$second->id}/move", ['after_id' => null]), RuntimeException::class, 'Simulated failure.');

        $this->assertSame([1, 2], [$first->fresh()->sort_index, $second->fresh()->sort_index]);
    }

    /** Replaces one repository method with a failure, keeping the rest real. */
    private function failing(string $interface, string $method, ?callable $before = null): void
    {
        $repository = Mockery::mock(resolve($interface))->makePartial();
        $repository->shouldReceive($method)->andReturnUsing(function (...$args) use ($before): void {
            $before && $before(...$args);

            throw new RuntimeException('Simulated failure.');
        });
        $this->app->instance($interface, $repository);
    }
}
