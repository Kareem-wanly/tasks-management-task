<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Enums\ProjectStatus;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Jobs\Bitrix\SyncBitrixProjectJob;
use App\Jobs\Bitrix\SyncBitrixTaskChatJob;
use App\Jobs\Bitrix\SyncBitrixTaskJob;
use App\Models\BitrixPortal;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BitrixEventsTest extends TestCase
{
    use RefreshDatabase;

    /** What the fake portal answers: Bitrix task/group ID => row ([] = deleted). */
    private array $tasks = [];

    private array $groups = [];

    private array $users = [];

    private bool $serviceIsAdmin = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        BitrixPortal::forceCreate(['member_id' => 'member-1', 'domain' => 'portal.test', 'application_token' => 'app-token']);

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

        Http::fake(function (Request $request) {
            $method = basename(parse_url($request->url(), PHP_URL_PATH), '.json');

            return Http::response(['result' => match ($method) {
                'tasks.task.get' => ($this->tasks[$request['taskId']] ?? []) === [] ? [] : ['task' => $this->tasks[$request['taskId']]],
                'socialnetwork.api.workgroup.list' => ['workgroups' => array_values(array_filter([$this->groups[$request['filter']['ID']] ?? null]))],
                'sonet_group.user.get' => [['USER_ID' => '5', 'ROLE' => 'A']],
                'user.get' => array_values(array_filter([$this->users[$request['FILTER']['ID']] ?? null])),
                'user.admin' => $this->serviceIsAdmin,
                default => true,
            }]);
        });
    }

    public function test_event_with_a_wrong_application_token_is_refused(): void
    {
        Queue::fake();

        $this->event('ONTASKUPDATE', ['FIELDS_AFTER' => ['ID' => 15]], 'forged')->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_task_event_queues_one_delayed_fetch_per_task(): void
    {
        Queue::fake();

        // Bitrix often sends several events for one change.
        $this->event('ONTASKUPDATE', ['FIELDS_AFTER' => ['ID' => 15]])->assertOk();
        $this->event('ONTASKUPDATE', ['FIELDS_AFTER' => ['ID' => 15]])->assertOk();
        $this->event('ONTASKDELETE', ['FIELDS_BEFORE' => ['ID' => 16]])->assertOk();

        Queue::assertPushedOn('bitrix', SyncBitrixTaskJob::class);
        Queue::assertPushed(SyncBitrixTaskJob::class, fn (SyncBitrixTaskJob $job): bool => $job->bitrixTaskId === 15 && $job->delay !== null);
        Queue::assertPushed(SyncBitrixTaskJob::class, 2);
    }

    public function test_group_event_queues_a_project_fetch(): void
    {
        Queue::fake();

        $this->event('ONSONETGROUPUPDATE', ['FIELDS' => ['ID' => '4']])->assertOk();

        Queue::assertPushed(SyncBitrixProjectJob::class, fn (SyncBitrixProjectJob $job): bool => $job->bitrixGroupId === 4);
    }

    public function test_chat_message_event_queues_a_chat_fetch(): void
    {
        Queue::fake();

        $this->event('ONTASKCOMMENTADD', ['FIELDS_AFTER' => ['ID' => 1, 'TASK_ID' => 15]])->assertOk();

        Queue::assertPushedOn('bitrix', SyncBitrixTaskChatJob::class);
        Queue::assertPushed(SyncBitrixTaskChatJob::class, fn (SyncBitrixTaskChatJob $job): bool => $job->bitrixTaskId === 15 && $job->delay !== null);
    }

    public function test_task_job_creates_or_updates_the_task(): void
    {
        $this->tasks[15] = $this->bitrixTask(15, ['title' => 'From Bitrix']);

        dispatch_sync(new SyncBitrixTaskJob(15));
        $this->assertSame('From Bitrix', Task::where('bitrix_task_id', 15)->sole()->title);

        $this->tasks[15]['title'] = 'Renamed in Bitrix';
        dispatch_sync(new SyncBitrixTaskJob(15));
        $this->assertSame('Renamed in Bitrix', Task::where('bitrix_task_id', 15)->sole()->title);
    }

    public function test_task_job_deletes_a_task_gone_from_bitrix(): void
    {
        $this->tasks[15] = $this->bitrixTask(15);
        dispatch_sync(new SyncBitrixTaskJob(15));

        $this->tasks[15] = [];
        dispatch_sync(new SyncBitrixTaskJob(15));

        $this->assertSame(0, Task::count());
        $this->assertSame(1, Task::onlyTrashed()->count());
    }

    /** Review A4: a non-admin service account may simply not see the task, so it isn't deleted. */
    public function test_task_job_deletes_nothing_when_the_service_account_is_not_an_admin(): void
    {
        $this->tasks[15] = $this->bitrixTask(15);
        dispatch_sync(new SyncBitrixTaskJob(15));

        $this->tasks[15] = [];
        $this->serviceIsAdmin = false;
        dispatch_sync(new SyncBitrixTaskJob(15));
        $this->groups[4] = ['id' => '4', 'name' => 'G', 'ownerId' => '5', 'closed' => 'N'];
        dispatch_sync(new SyncBitrixProjectJob(4));
        unset($this->groups[4]);
        dispatch_sync(new SyncBitrixProjectJob(4));

        $this->assertSame(1, Task::count());
        $this->assertSame(1, Project::where('bitrix_group_id', 4)->count());
    }

    public function test_task_job_brings_in_a_new_group_and_a_new_user_first(): void
    {
        $this->users[7] = ['ID' => '7', 'ACTIVE' => true, 'NAME' => 'New', 'EMAIL' => 'new@portal.test'];
        $this->groups[9] = ['id' => '9', 'name' => 'Fresh group', 'ownerId' => '5', 'closed' => 'N'];
        $this->tasks[15] = $this->bitrixTask(15, ['responsibleId' => '7', 'groupId' => '9']);

        dispatch_sync(new SyncBitrixTaskJob(15));

        $task = Task::where('bitrix_task_id', 15)->sole();
        $this->assertSame('Fresh group', $task->project->title);
        $this->assertSame(7, $task->assignee->bitrix_user_id);
    }

    public function test_project_job_follows_archiving_and_deletion(): void
    {
        $this->groups[4] = ['id' => '4', 'name' => 'Group', 'ownerId' => '5', 'closed' => 'Y'];
        dispatch_sync(new SyncBitrixProjectJob(4));
        $this->assertSame(ProjectStatus::Archived, Project::sole()->status);

        unset($this->groups[4]);
        dispatch_sync(new SyncBitrixProjectJob(4));
        $this->assertSame(0, Project::count());
    }

    private function event(string $event, array $data, string $token = 'app-token'): TestResponse
    {
        return $this->post('/api/bitrix/events', [
            'event' => $event,
            'data' => $data,
            'auth' => ['application_token' => $token, 'member_id' => 'member-1', 'domain' => 'portal.test'],
        ]);
    }

    private function bitrixTask(int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => (string) $id, 'title' => "Task {$id}", 'status' => '2', 'priority' => '1',
            'createdBy' => '5', 'responsibleId' => '6', 'groupId' => '0',
        ], $overrides);
    }
}
