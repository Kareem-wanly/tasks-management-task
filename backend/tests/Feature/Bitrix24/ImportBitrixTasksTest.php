<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Jobs\Bitrix\ImportBitrixTasksJob;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportBitrixTasksTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array> Every task the fake portal holds. */
    private array $tasks = [];

    /** Tasks the listing leaves out but tasks.task.get still returns (e.g. hidden from the listing). */
    private array $stillThere = [];

    private bool $serviceIsAdmin = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);

        app()->instance(BitrixGatewayInterface::SERVICE_ACCOUNT, new class implements HasBitrixToken
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

        foreach ([5, 6] as $bitrixId) {
            User::factory()->create(['bitrix_user_id' => $bitrixId]);
        }
        Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->user(5)->id, 'bitrix_group_id' => 3]);

        // Serves the open (!STATUS=5) and closed (STATUS=5) passes from $this->tasks.
        Http::fake([
            'https://portal.test/rest/tasks.task.list.json' => function (Request $request) {
                $closed = array_key_exists('STATUS', $request['filter']);
                $rows = array_values(array_filter($this->tasks, fn (array $t): bool => ($t['status'] === '5') === $closed));

                return Http::response(['result' => ['tasks' => $rows], 'total' => count($rows)]);
            },
            // A deleted task answers an empty result (checked on the real portal).
            'https://portal.test/rest/tasks.task.get.json' => function (Request $request) {
                $id = (int) $request['taskId'];
                $found = in_array($id, $this->stillThere, true)
                    || array_filter($this->tasks, fn (array $t): bool => (int) $t['id'] === $id) !== [];

                return Http::response(['result' => $found ? ['task' => $this->task($id)] : []]);
            },
            'https://portal.test/rest/user.admin.json' => fn () => Http::response(['result' => $this->serviceIsAdmin]),
        ]);
    }

    public function test_task_fields_are_mapped(): void
    {
        $this->tasks = [$this->task(15, ['status' => '3', 'priority' => '2', 'description' => '[b]Hi[/b]'])];

        $this->import();

        $task = Task::where('bitrix_task_id', 15)->sole();
        $this->assertSame('Task 15', $task->title);
        $this->assertSame('[b]Hi[/b]', $task->description);
        $this->assertSame('in_progress', $task->status);
        // The Bitrix row (high) wins over our custom urgent, which also sends 2.
        $this->assertSame('high', $task->priority);
        $this->assertSame(Project::sole()->id, $task->project_id);
        $this->assertSame($this->user(5)->id, $task->created_by);
        $this->assertSame($this->user(6)->id, $task->assigned_to);
        $this->assertSame('2026-11-19 16:00:00', $task->due_date->utc()->toDateTimeString());
        $this->assertSame('2026-11-12 12:27:12', $task->created_at->utc()->toDateTimeString());
        $this->assertNull($task->completed_at);
        $this->assertNotNull($task->bitrix_synced_at);
    }

    public function test_job_runs_on_the_bitrix_queue(): void
    {
        $this->assertSame('bitrix', (new ImportBitrixTasksJob)->queue);
    }

    public function test_open_tasks_are_imported_before_closed_ones(): void
    {
        $this->tasks = [
            $this->task(19, ['status' => '5', 'closedDate' => '2026-11-12T15:30:46+03:00']),
            $this->task(15),
        ];

        $this->import();

        $this->assertSame([15, 19], Task::orderBy('id')->pluck('bitrix_task_id')->all());
        $closed = Task::where('bitrix_task_id', 19)->sole();
        $this->assertSame('completed', $closed->status);
        $this->assertSame('2026-11-12 12:30:46', $closed->completed_at->utc()->toDateTimeString());
        Http::assertSent(fn (Request $r): bool => ($r['filter']['!STATUS'] ?? null) === 5);
    }

    public function test_legacy_new_status_is_treated_as_pending(): void
    {
        $this->tasks = [$this->task(15, ['status' => '1'])];

        $this->import();

        $this->assertSame('todo', Task::sole()->status);
    }

    public function test_task_without_project_is_imported_with_no_project(): void
    {
        $this->tasks = [$this->task(15, ['groupId' => '0'])];

        $this->import();

        $this->assertNull(Task::sole()->project_id);
    }

    public function test_running_twice_updates_without_duplicates_or_log_noise(): void
    {
        $this->tasks = [$this->task(15)];
        $this->import();
        $logs = ActivityLog::count();

        $this->import();
        $this->assertSame($logs, ActivityLog::count());

        $this->tasks = [$this->task(15, ['title' => 'Renamed', 'responsibleId' => '5'])];
        $this->import();

        $task = Task::sole();
        $this->assertSame('Renamed', $task->title);
        $this->assertSame($this->user(5)->id, $task->assigned_to);
    }

    public function test_task_with_unknown_creator_or_project_is_skipped(): void
    {
        $this->tasks = [
            $this->task(15, ['createdBy' => '99']),
            $this->task(16, ['groupId' => '42']),
            $this->task(17, ['responsibleId' => '99']),
        ];

        $this->import();

        $this->assertSame([17], Task::pluck('bitrix_task_id')->all());
        // An unknown responsible person only drops the assignee.
        $this->assertNull(Task::sole()->assigned_to);
    }

    public function test_task_deleted_here_is_restored_while_it_exists_in_bitrix(): void
    {
        $this->tasks = [$this->task(15)];
        $this->import();
        Task::sole()->delete();

        $this->import();

        $this->assertSame(1, Task::count());
    }

    public function test_a_full_import_deletes_tasks_gone_from_bitrix(): void
    {
        $this->tasks = [$this->task(15), $this->task(16)];
        $this->import();

        // 16 was deleted in Bitrix while its event was lost.
        $this->tasks = [$this->task(15)];
        $this->import();

        $this->assertSame([15], Task::pluck('bitrix_task_id')->all());
        $this->assertSoftDeleted(Task::withTrashed()->where('bitrix_task_id', 16)->sole());
    }

    /** Review A4: a non-admin service account doesn't see every task, so nothing is deleted. */
    public function test_nothing_is_deleted_when_the_service_account_is_not_an_admin(): void
    {
        $this->tasks = [$this->task(15), $this->task(16)];
        $this->import();

        $this->tasks = [$this->task(15)];
        $this->serviceIsAdmin = false;
        $this->import();

        $this->assertSame(2, Task::count());
    }

    /** Review A4: missing from the listing is not proof; Bitrix must confirm it is gone. */
    public function test_a_task_bitrix_still_returns_is_not_deleted(): void
    {
        $this->tasks = [$this->task(15), $this->task(16)];
        $this->import();

        $this->tasks = [$this->task(15)];
        $this->stillThere = [16];
        $this->import();

        $this->assertSame(2, Task::count());
    }

    /** Review A4: a mass deletion is far more likely a fault, so a run deletes at most 20. */
    public function test_too_many_missing_tasks_deletes_none(): void
    {
        $this->tasks = array_map(fn (int $id): array => $this->task($id), range(100, 125));
        $this->import();

        $this->tasks = [$this->task(100)];
        $this->import();

        $this->assertSame(26, Task::count());
    }

    public function test_an_empty_listing_deletes_nothing(): void
    {
        $this->tasks = [$this->task(15)];
        $this->import();

        $this->tasks = [];
        $this->import();

        $this->assertSame(1, Task::count());
    }

    public function test_a_delta_asks_only_for_changed_tasks_and_deletes_nothing(): void
    {
        $this->tasks = [$this->task(15), $this->task(16)];
        $this->import();

        $this->tasks = [$this->task(15, ['title' => 'Changed in Bitrix'])];
        dispatch_sync(new ImportBitrixTasksJob(CarbonImmutable::parse('2026-11-15 12:00:00', 'UTC')));

        $this->assertSame('Changed in Bitrix', Task::where('bitrix_task_id', 15)->sole()->title);
        $this->assertSame(2, Task::count());
        Http::assertSent(fn (Request $r): bool => ($r['filter']['>CHANGED_DATE'] ?? null) === '2026-11-15T12:00:00+00:00');
    }

    public function test_re_importing_an_unchanged_task_keeps_its_updated_at(): void
    {
        $this->tasks = [$this->task(15)];
        $this->import();
        $updatedAt = Task::sole()->updated_at->toDateTimeString();

        $this->travel(1)->hours();
        $this->import();

        $task = Task::sole();
        $this->assertSame($updatedAt, $task->updated_at->toDateTimeString());
        $this->assertSame(now()->toDateTimeString(), $task->bitrix_synced_at->toDateTimeString());
    }

    private function task(int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => (string) $id,
            'title' => "Task {$id}",
            'description' => '',
            'status' => '2',
            'priority' => '1',
            'responsibleId' => '6',
            'createdBy' => '5',
            'groupId' => '3',
            'deadline' => '2026-11-19T19:00:00+03:00',
            'createdDate' => '2026-11-12T15:27:12+03:00',
            'closedDate' => null,
        ], $overrides);
    }

    private function user(int $bitrixId): User
    {
        return User::where('bitrix_user_id', $bitrixId)->first();
    }

    private function import(): void
    {
        dispatch_sync(new ImportBitrixTasksJob);
    }
}
