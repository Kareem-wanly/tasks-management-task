<?php

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Jobs\Bitrix\ImportBitrixProjects;
use App\Jobs\Bitrix\ImportBitrixTasks;
use App\Jobs\Bitrix\ImportBitrixUsers;
use App\Models\BitrixSyncCursor;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportFromBitrixCommandTest extends TestCase
{
    use RefreshDatabase;

    private bool $tasksFail = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);

        app()->instance(BitrixClient::SERVICE_ACCOUNT, new class implements HasBitrixToken
        {
            public function bitrixToken(): ?BitrixToken
            {
                return new BitrixToken('service-access', 'service-refresh', CarbonImmutable::now()->addHour());
            }

            public function storeBitrixToken(BitrixToken $token): void {}

            public function bitrixTokenLockKey(): string
            {
                return 'bitrix-token:test';
            }
        });

        // A tiny portal: two users, one group, one open and one closed task.
        Http::fake([
            'https://portal.test/rest/user.get.json' => Http::response(['result' => [
                ['ID' => '1', 'ACTIVE' => true, 'NAME' => 'Owner', 'EMAIL' => 'owner@portal.test'],
                ['ID' => '2', 'ACTIVE' => true, 'NAME' => 'Member', 'EMAIL' => 'member@portal.test'],
            ], 'total' => 2]),
            'https://portal.test/rest/socialnetwork.api.workgroup.list.json' => Http::response([
                'result' => ['workgroups' => [['id' => '7', 'name' => 'Group', 'ownerId' => '1', 'closed' => 'N']]],
                'total' => 1,
            ]),
            'https://portal.test/rest/batch.json' => Http::response(['result' => [
                'result' => ['group_7' => [['USER_ID' => '1', 'ROLE' => 'A'], ['USER_ID' => '2', 'ROLE' => 'K']]],
                'result_error' => [],
            ]]),
            'https://portal.test/rest/tasks.task.list.json' => function (Request $request) {
                if ($this->tasksFail) {
                    return Http::response(['error' => 'ACCESS_DENIED', 'error_description' => 'denied'], 403);
                }
                $closed = array_key_exists('STATUS', $request['filter']);

                return Http::response(['result' => ['tasks' => [[
                    'id' => $closed ? '2' : '1',
                    'title' => 'Task',
                    'status' => $closed ? '5' : '2',
                    'priority' => '1',
                    'createdBy' => '1',
                    'responsibleId' => '2',
                    'groupId' => $closed ? '0' : '7',
                ]]], 'total' => 1]);
            },
        ]);
    }

    public function test_sync_run_imports_everything_in_order_and_records_progress(): void
    {
        $this->artisan('bitrix:import --sync')->assertSuccessful();

        $this->assertSame(2, User::whereNotNull('bitrix_user_id')->count());
        $this->assertSame(1, Project::whereNotNull('bitrix_group_id')->count());
        $this->assertSame(2, Task::whereNotNull('bitrix_task_id')->count());

        foreach (['users' => 2, 'projects' => 1, 'tasks' => 2] as $entity => $count) {
            $cursor = BitrixSyncCursor::where('entity', $entity)->sole();
            $this->assertSame(BitrixSyncCursor::COMPLETED, $cursor->status);
            $this->assertSame($count, $cursor->processed);
            $this->assertSame($count, $cursor->total);
            $this->assertNotNull($cursor->last_synced_at);
        }
    }

    public function test_running_it_again_creates_nothing_new(): void
    {
        $this->artisan('bitrix:import --sync')->assertSuccessful();
        $counts = [User::count(), Project::count(), Task::count(), Project::sole()->members()->count()];

        $this->artisan('bitrix:import --sync')->assertSuccessful();

        $this->assertSame($counts, [User::count(), Project::count(), Task::count(), Project::sole()->members()->count()]);
    }

    public function test_only_runs_the_named_parts_in_dependency_order(): void
    {
        Bus::fake();

        $this->artisan('bitrix:import --only=tasks,users')->assertSuccessful();

        Bus::assertChained([ImportBitrixUsers::class, ImportBitrixTasks::class]);
    }

    public function test_default_run_queues_the_full_chain_on_the_bitrix_queue(): void
    {
        Bus::fake();

        $this->artisan('bitrix:import')->assertSuccessful();

        Bus::assertChained([ImportBitrixUsers::class, ImportBitrixProjects::class, ImportBitrixTasks::class]);
        Bus::assertDispatched(ImportBitrixUsers::class, fn (ImportBitrixUsers $job) => $job->queue === 'bitrix');
    }

    public function test_unknown_only_value_is_rejected(): void
    {
        Bus::fake();

        $this->artisan('bitrix:import --only=users,deals')->assertFailed();

        Bus::assertNothingDispatched();
    }

    public function test_a_failed_part_is_recorded_and_the_command_fails(): void
    {
        $this->tasksFail = true;

        $this->artisan('bitrix:import --sync')->assertFailed();

        $this->assertSame(BitrixSyncCursor::COMPLETED, BitrixSyncCursor::where('entity', 'projects')->value('status'));
        $tasks = BitrixSyncCursor::where('entity', 'tasks')->sole();
        $this->assertSame(BitrixSyncCursor::FAILED, $tasks->status);
        $this->assertStringContainsString('denied', $tasks->error);
    }

    public function test_status_lists_every_part(): void
    {
        $this->artisan('bitrix:import --status')
            ->expectsTable(['Entity', 'Status', 'Processed', 'Total', 'Last synced', 'Error'], [
                ['users', 'never run', 0, 0, '-', ''],
                ['projects', 'never run', 0, 0, '-', ''],
                ['tasks', 'never run', 0, 0, '-', ''],
            ])
            ->assertSuccessful();
    }

    public function test_sync_changes_imports_everything_when_never_synced(): void
    {
        $this->artisan('bitrix:sync-changes')->assertSuccessful();

        $this->assertSame(2, User::whereNotNull('bitrix_user_id')->count());
        $this->assertSame(2, Task::whereNotNull('bitrix_task_id')->count());
    }

    public function test_sync_changes_asks_for_what_changed_since_the_last_sync(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'UTC'));
        $this->artisan('bitrix:import --sync')->assertSuccessful();
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:05:00', 'UTC'));

        $this->artisan('bitrix:sync-changes')->assertSuccessful();

        // A minute of overlap with the previous run.
        Http::assertSent(fn (Request $r) => ($r['filter']['>CHANGED_DATE'] ?? null) === '2026-11-15T11:59:00+00:00');
        // The next run starts from when this one started.
        $this->assertSame('2026-11-15 12:05:00', BitrixSyncCursor::where('entity', 'tasks')->sole()->last_synced_at->utc()->toDateTimeString());
        $this->assertSame(2, Task::count());
    }

    public function test_the_safety_net_is_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('bitrix:sync-changes')
            ->expectsOutputToContain('bitrix:import --sync')
            ->assertSuccessful();
    }
}
