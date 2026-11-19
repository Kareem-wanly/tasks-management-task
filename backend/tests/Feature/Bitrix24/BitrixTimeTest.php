<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixToken;
use App\Jobs\Bitrix\ImportBitrixTime;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\RunningTimer;
use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Models\User;
use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Step 4.4: time log and timer.
 */
class BitrixTimeTest extends TestCase
{
    use RefreshDatabase;

    /** Access token => Bitrix user ID. */
    private const TOKENS = ['actor-access' => 5, 'assignee-access' => 7, 'auditor-access' => 8];

    private User $actor;

    private Project $project;

    private Task $task;

    /** @var array<int, array<string, mixed>> The fake Bitrix time log of every task, by entry ID. */
    private array $log = [];

    private int $nextId = 1;

    /** null, 'unreachable' or 'refuse'. */
    private ?string $failure = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actor = $this->user(5, 'actor', Role::where('name', 'Administrator')->first());
        app()->instance(BitrixClient::SERVICE_ACCOUNT, $this->actor);

        $this->project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 3]);
        $this->task = $this->task(15);

        Http::fake(function (Request $request) {
            if ($this->failure === 'unreachable') {
                throw new ConnectionException('timed out');
            }
            if ($this->failure === 'refuse') {
                return Http::response(['error' => 'ACCESS_DENIED', 'error_description' => 'Access denied'], 403);
            }

            return Http::response(['result' => $this->bitrix(basename(parse_url($request->url(), PHP_URL_PATH), '.json'), $request->data())]);
        });
        Sanctum::actingAs($this->actor);
    }

    public function test_opening_the_task_brings_in_the_bitrix_time_log(): void
    {
        $this->entry(15, 5, 600, 'Research');
        $this->entry(15, 99, 60);

        $this->getJson("/api/v1/tasks/{$this->task->id}/time")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.user.id', $this->actor->id)
            ->assertJsonPath('data.1.comment', 'Research')
            // Its CREATED_DATE, not the shifted DATE_START.
            ->assertJsonPath('data.1.created_date', '2026-11-16T11:23:52.000000Z')
            ->assertJsonPath('meta.time_spent', 660)
            ->assertJsonPath('meta.can_log', true);
    }

    public function test_bitrix_unreachable_shows_our_last_copy(): void
    {
        $this->entry(15, 5, 600);
        $this->getJson("/api/v1/tasks/{$this->task->id}/time");
        $this->failure = 'unreachable';

        $this->getJson("/api/v1/tasks/{$this->task->id}/time")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.stale', true);
    }

    public function test_time_is_logged_edited_and_deleted_as_the_employee(): void
    {
        $this->postJson("/api/v1/tasks/{$this->task->id}/time", ['hours' => 1, 'minutes' => 30, 'comment' => 'Design'])
            ->assertOk()
            ->assertJsonPath('meta.time_spent', 5400);
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'task.elapseditem.add.json')
            && $r['auth'] === 'actor-access' && $r['ARFIELDS'] === ['SECONDS' => 5400, 'COMMENT_TEXT' => 'Design']);

        $entry = TaskElapsedItem::sole();
        $this->putJson("/api/v1/time-entries/{$entry->id}", ['hours' => 0, 'minutes' => 45, 'comment' => null])->assertJsonPath('meta.time_spent', 2700);
        $this->deleteJson("/api/v1/time-entries/{$entry->id}")->assertJsonCount(0, 'data')->assertJsonPath('meta.time_spent', 0);
    }

    public function test_tracking_must_be_on_and_can_be_turned_on(): void
    {
        $this->task->forceFill(['allow_time_tracking' => false])->save();

        $this->postJson("/api/v1/tasks/{$this->task->id}/time", ['hours' => 1, 'minutes' => 0])->assertJsonValidationErrors('task');
        $this->postJson("/api/v1/tasks/{$this->task->id}/timer")->assertJsonValidationErrors('task');

        $this->putJson("/api/v1/tasks/{$this->task->id}", ['allow_time_tracking' => true, 'time_estimate' => 7200])->assertOk();
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.update.json')
            && $r['fields'] === ['ALLOW_TIME_TRACKING' => 'Y', 'TIME_ESTIMATE' => 7200]);
    }

    public function test_tasks_made_here_track_time(): void
    {
        $this->postJson("/api/v1/projects/{$this->project->id}/tasks", ['title' => 'New'])->assertCreated()->assertJsonPath('data.allow_time_tracking', true);

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.add.json') && $r['fields']['ALLOW_TIME_TRACKING'] === 'Y');
    }

    public function test_stopping_the_timer_logs_the_time_in_bitrix(): void
    {
        $this->postJson("/api/v1/tasks/{$this->task->id}/timer")->assertOk()->assertJsonPath('meta.timer.task_id', $this->task->id);
        $this->travel(25)->minutes();

        $this->postJson("/api/v1/tasks/{$this->task->id}/timer/stop", ['comment' => 'Focus'])
            ->assertOk()
            ->assertJsonPath('meta.timer', null)
            ->assertJsonPath('meta.time_spent', 1500);
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'task.elapseditem.add.json') && $r['ARFIELDS'] === ['SECONDS' => 1500, 'COMMENT_TEXT' => 'Focus']);
    }

    /** Review B2: a double click (or a second tab) on Stop logs the time once. */
    public function test_a_second_stop_logs_nothing_more(): void
    {
        $this->postJson("/api/v1/tasks/{$this->task->id}/timer");
        $this->travel(25)->minutes();

        $this->postJson("/api/v1/tasks/{$this->task->id}/timer/stop")->assertOk();
        $this->postJson("/api/v1/tasks/{$this->task->id}/timer/stop")->assertOk()->assertJsonPath('meta.timer', null);

        $this->assertCount(1, Http::recorded(fn (Request $r): bool => str_ends_with($r->url(), 'task.elapseditem.add.json')));
    }

    /** Review B2: of two requests stopping the same timer at once, only one gets to log it. */
    public function test_a_timer_can_be_claimed_only_once(): void
    {
        $this->postJson("/api/v1/tasks/{$this->task->id}/timer");
        $timer = RunningTimer::sole();
        $entries = resolve(TimeEntryRepositoryInterface::class);

        $this->assertTrue($entries->claimTimer($timer));
        $this->assertFalse($entries->claimTimer($timer));
    }

    /** Review B2: two requests starting a timer at once answer 409, not 500. */
    public function test_starting_twice_at_once_is_a_conflict_not_a_server_error(): void
    {
        RunningTimer::create(['user_id' => $this->actor->id, 'task_id' => $this->task->id, 'started_at' => now()]);
        // The other request's timer appears after this one looked for a running timer.
        $this->replaceMethod(TimeEntryRepositoryInterface::class, 'runningTimer', fn (): null => null);

        $this->postJson("/api/v1/tasks/{$this->task->id}/timer")->assertStatus(409);

        $this->assertSame(1, RunningTimer::count());
    }

    /** Review B2: Stop on a task only stops the timer running on that task. */
    public function test_stop_on_another_task_is_refused(): void
    {
        $other = $this->task(16);
        $this->postJson("/api/v1/tasks/{$other->id}/timer");
        $this->travel(5)->minutes();

        $this->postJson("/api/v1/tasks/{$this->task->id}/timer/stop")->assertUnprocessable();

        $this->assertSame($other->id, RunningTimer::sole()->task_id);
        Http::assertNotSent(fn (Request $r): bool => str_ends_with($r->url(), 'task.elapseditem.add.json'));
    }

    public function test_starting_a_timer_stops_and_logs_the_running_one(): void
    {
        $other = $this->task(16);
        $this->postJson("/api/v1/tasks/{$this->task->id}/timer");
        $this->travel(10)->minutes();

        $this->postJson("/api/v1/tasks/{$other->id}/timer")
            ->assertJsonPath('meta.timer.task_id', $other->id)
            ->assertJsonPath('meta.stopped_task.id', $this->task->id);

        $this->assertSame(600, $this->task->fresh()->time_spent);
        $this->assertSame($other->id, RunningTimer::sole()->task_id);
    }

    public function test_the_timer_keeps_running_when_bitrix_refuses_the_time(): void
    {
        $this->postJson("/api/v1/tasks/{$this->task->id}/timer");
        $this->travel(5)->minutes();
        $this->failure = 'refuse';

        $this->postJson("/api/v1/tasks/{$this->task->id}/timer/stop")->assertForbidden();

        $this->assertSame(1, RunningTimer::count());
    }

    public function test_the_people_on_the_task_log_time_but_only_change_their_own(): void
    {
        $viewOnly = Role::create(['name' => 'Viewer', 'display_name' => 'Viewer', 'description' => 'x']);
        $viewOnly->permissions()->sync(Permission::where('name', 'tasks.view')->pluck('id'));
        $assignee = $this->user(7, 'assignee', $viewOnly);
        $auditor = $this->user(8, 'auditor', $viewOnly);
        $this->task->forceFill(['assigned_to' => $assignee->id])->save();
        $this->task->auditors()->sync([$auditor->id]);
        $this->entry(15, 5, 600);
        $this->getJson("/api/v1/tasks/{$this->task->id}/time");
        $actorsEntry = TaskElapsedItem::sole();

        Sanctum::actingAs($auditor);
        $this->postJson("/api/v1/tasks/{$this->task->id}/time", ['hours' => 1, 'minutes' => 0])->assertForbidden();

        Sanctum::actingAs($assignee);
        $this->postJson("/api/v1/tasks/{$this->task->id}/time", ['hours' => 1, 'minutes' => 0])
            ->assertOk()
            ->assertJsonPath('data.0.can_edit', true)
            ->assertJsonPath('data.1.can_edit', false);
        $this->deleteJson("/api/v1/time-entries/{$actorsEntry->id}")->assertForbidden();
    }

    public function test_the_nightly_pass_brings_in_every_tasks_time_log(): void
    {
        $this->entry(15, 5, 900);

        dispatch_sync(new ImportBitrixTime);

        $this->assertSame(900, $this->task->fresh()->time_spent);
    }

    private function user(int $bitrixId, string $name, Role $role): User
    {
        $user = User::factory()->create(['bitrix_user_id' => $bitrixId]);
        $user->roles()->attach($role);
        $user->storeBitrixToken(new BitrixToken("{$name}-access", "{$name}-refresh", CarbonImmutable::now()->addHour()));

        return $user;
    }

    private function task(int $bitrixId, bool $tracking = true): Task
    {
        return Task::forceCreate([
            'title' => "Task {$bitrixId}", 'status' => 'todo', 'priority' => 'medium', 'project_id' => $this->project->id,
            'created_by' => $this->actor->id, 'assigned_to' => $this->actor->id, 'bitrix_task_id' => $bitrixId,
            'allow_time_tracking' => $tracking,
        ]);
    }

    private function bitrix(string $method, array $data): mixed
    {
        $id = (int) ($data['ITEMID'] ?? 0);
        $mine = fn ($taskId): array => array_values(array_filter($this->log, fn (array $e): bool => $e['TASK_ID'] === (string) $taskId));

        return match ($method) {
            'task.elapseditem.getlist' => $mine($data['TASKID']),
            'task.elapseditem.add' => $this->entry((int) $data['TASKID'], self::TOKENS[$data['auth']], $data['ARFIELDS']['SECONDS'], $data['ARFIELDS']['COMMENT_TEXT']),
            'task.elapseditem.update' => (bool) ($this->log[$id] = [...$this->log[$id], 'SECONDS' => (string) $data['ARFIELDS']['SECONDS'], 'COMMENT_TEXT' => $data['ARFIELDS']['COMMENT_TEXT']]),
            'task.elapseditem.delete' => (function () use ($id): true {
                unset($this->log[$id]);

                return true;
            })(),
            'tasks.task.add' => ['task' => ['id' => '99', 'status' => '2']],
            // Batch commands arrive as "method?query".
            'batch' => ['result' => array_map(function (string $cmd) use ($mine): array {
                parse_str((string) parse_url($cmd, PHP_URL_QUERY), $params);

                return $mine($params['TASKID']);
            }, $data['cmd']), 'result_error' => []],
            default => true,
        };
    }

    private function entry(int $taskId, int $userId, int $seconds, string $comment = ''): int
    {
        $id = $this->nextId++;
        $this->log[$id] = ['ID' => (string) $id, 'TASK_ID' => (string) $taskId, 'USER_ID' => (string) $userId, 'SECONDS' => (string) $seconds,
            'COMMENT_TEXT' => $comment, 'CREATED_DATE' => '2026-11-16T11:23:52+00:00', 'DATE_START' => '2026-11-16T08:23:52+00:00'];

        return $id;
    }
}
