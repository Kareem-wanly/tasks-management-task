<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixToken;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BitrixTaskPushTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $assignee;

    private Project $project;

    /** Status tasks.task.complete ends in: 5, or 4 when the creator must approve. */
    private string $completeStatus = '5';

    /** null = Bitrix answers normally; 'refuse' or 'unreachable' simulate failures. */
    private ?string $failure = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = Role::where('name', 'Administrator')->first();

        $this->actor = User::factory()->create(['bitrix_user_id' => 5]);
        $this->actor->roles()->attach($admin);
        $this->actor->storeBitrixToken(new BitrixToken('actor-access', 'actor-refresh', CarbonImmutable::now()->addHour()));
        $this->assignee = User::factory()->create(['bitrix_user_id' => 6]);

        $this->project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 3]);
        $this->project->members()->attach($this->assignee->id, ['role' => 'member']);

        Http::fake(function (Request $request) {
            if ($this->failure === 'unreachable') {
                throw new ConnectionException('timed out');
            }
            if ($this->failure === 'refuse') {
                return Http::response(['error' => 'ACCESS_DENIED', 'error_description' => 'Access denied'], 403);
            }

            $method = basename(parse_url($request->url(), PHP_URL_PATH), '.json');
            $status = ['tasks.task.start' => '3', 'tasks.task.pause' => '2', 'tasks.task.renew' => '2',
                'tasks.task.defer' => '6', 'tasks.task.complete' => $this->completeStatus,
                'tasks.task.approve' => '5', 'tasks.task.disapprove' => '2', 'tasks.task.add' => '2'][$method] ?? null;

            return Http::response(['result' => ['task' => array_filter(['id' => $method === 'tasks.task.add' ? '100' : null, 'status' => $status])]]);
        });
    }

    public function test_new_task_is_created_in_bitrix_as_the_employee(): void
    {
        Sanctum::actingAs($this->actor);

        $this->postJson("/api/projects/{$this->project->id}/tasks", [
            'title' => 'Write docs', 'priority' => 'high', 'due_date' => '2026-12-01', 'assigned_to' => $this->assignee->id,
        ])->assertCreated();

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.add.json')
            && $r['auth'] === 'actor-access'
            && $r['fields']['TITLE'] === 'Write docs'
            && $r['fields']['PRIORITY'] === 2
            && $r['fields']['GROUP_ID'] === 3
            && $r['fields']['RESPONSIBLE_ID'] === 6
            && str_starts_with($r['fields']['DEADLINE'], '2026-12-01'));
        $this->assertSame(100, Task::sole()->bitrix_task_id);
    }

    public function test_new_task_in_progress_is_started_after_creation(): void
    {
        Sanctum::actingAs($this->actor);

        $this->postJson("/api/projects/{$this->project->id}/tasks", ['title' => 'X', 'status' => 'in_progress'])->assertCreated();

        $this->assertSame(['tasks.task.add', 'tasks.task.start'], $this->calls());
        $this->assertSame('in_progress', Task::sole()->status);
        // No assignee given: Bitrix needs one, so it is the creator.
        $this->assertSame($this->actor->id, Task::sole()->assigned_to);
    }

    public function test_task_without_project_is_created_with_group_zero(): void
    {
        Sanctum::actingAs($this->actor);

        $this->postJson('/api/tasks', ['title' => 'Personal'])->assertCreated()->assertJsonPath('data.project', null);

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.add.json') && $r['fields']['GROUP_ID'] === 0);
        $this->assertNull(Task::sole()->project_id);
    }

    public function test_update_sends_only_the_changed_fields(): void
    {
        $task = $this->bitrixTask();
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Renamed', 'priority' => 'medium'])->assertOk();

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.update.json')
            && $r['taskId'] === 100 && $r['fields'] === ['TITLE' => 'Renamed']);
        $this->assertSame('Renamed', $task->fresh()->title);
    }

    public function test_status_changes_use_actions_not_the_status_field(): void
    {
        $task = $this->bitrixTask();
        Sanctum::actingAs($this->actor);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertOk();
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'todo'])->assertOk();
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'completed'])->assertOk();
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'deferred'])->assertOk();

        $this->assertSame(['tasks.task.start', 'tasks.task.pause', 'tasks.task.complete', 'tasks.task.renew', 'tasks.task.defer'], $this->calls());
        $this->assertSame('deferred', $task->fresh()->status);
    }

    public function test_bitrix_decides_the_final_status(): void
    {
        $this->completeStatus = '4';
        $task = $this->bitrixTask(['status' => 'in_progress']);
        Sanctum::actingAs($this->actor);

        // The task needs the creator's approval, so Bitrix stops at "awaiting control".
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'completed'])->assertOk()->assertJsonPath('data.status', 'review');
    }

    public function test_creator_approves_a_task_that_awaits_review(): void
    {
        $task = $this->bitrixTask(['status' => 'review']);
        Sanctum::actingAs($this->actor);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'completed'])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertSame(['tasks.task.approve'], $this->calls());
    }

    public function test_delete_removes_the_task_in_bitrix(): void
    {
        $task = $this->bitrixTask();
        Sanctum::actingAs($this->actor);

        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.delete.json') && $r['taskId'] === 100);
        $this->assertSoftDeleted($task);
    }

    public function test_employee_without_a_bitrix_token_cannot_change_bitrix_tasks(): void
    {
        $task = $this->bitrixTask();
        $local = User::factory()->create();
        $local->roles()->attach(Role::where('name', 'Administrator')->first());
        Sanctum::actingAs($local);

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Renamed'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sign in with Bitrix24 to change data that is synced with Bitrix24.');

        $this->assertSame('T', $task->fresh()->title);
        Http::assertNothingSent();
    }

    public function test_employee_without_a_bitrix_token_is_told_to_sign_in_when_creating(): void
    {
        $local = User::factory()->create(['name' => 'tester']);
        $local->roles()->attach(Role::where('name', 'Administrator')->first());
        Sanctum::actingAs($local);

        // No assignee, so the creator would become responsible; the sign-in reason still comes first.
        $this->postJson('/api/tasks', ['title' => 'Personal'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sign in with Bitrix24 to change data that is synced with Bitrix24.');

        $this->assertSame(0, Task::count());
    }

    public function test_a_bitrix_refusal_saves_nothing(): void
    {
        $task = $this->bitrixTask();
        $this->failure = 'refuse';
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Renamed'])->assertForbidden();

        $this->assertSame('T', $task->fresh()->title);
    }

    public function test_bitrix_not_responding_saves_nothing(): void
    {
        $task = $this->bitrixTask();
        $this->failure = 'unreachable';
        Sanctum::actingAs($this->actor);

        $this->deleteJson("/api/tasks/{$task->id}")->assertStatus(503);

        $this->assertNotSoftDeleted($task);
    }

    public function test_assignee_who_is_not_in_bitrix_is_refused(): void
    {
        $task = $this->bitrixTask();
        $local = User::factory()->create(['name' => 'Local Only']);
        $this->project->members()->attach($local->id, ['role' => 'member']);
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/tasks/{$task->id}", ['assigned_to' => $local->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Local Only is not a Bitrix24 user, so the task cannot be assigned to them.');
    }

    public function test_tasks_of_local_only_projects_stay_local(): void
    {
        $local = Project::forceCreate(['title' => 'Demo', 'status' => 'active', 'owner_id' => $this->actor->id]);
        Sanctum::actingAs($this->actor);

        $this->postJson("/api/projects/{$local->id}/tasks", ['title' => 'Demo task'])->assertCreated();

        Http::assertNothingSent();
        $this->assertNull(Task::sole()->bitrix_task_id);
    }

    private function bitrixTask(array $attributes = []): Task
    {
        return Task::forceCreate(array_merge([
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'project_id' => $this->project->id,
            'created_by' => $this->actor->id, 'assigned_to' => $this->assignee->id, 'bitrix_task_id' => 100,
        ], $attributes));
    }

    /** @return list<string> Bitrix methods called, in order. */
    private function calls(): array
    {
        return Http::recorded()->map(fn (array $pair): string => basename(parse_url($pair[0]->url(), PHP_URL_PATH), '.json'))->all();
    }
}
