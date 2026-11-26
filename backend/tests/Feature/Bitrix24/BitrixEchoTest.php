<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Enums\OutboundOpStatus;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Jobs\Bitrix\SyncBitrixProject;
use App\Jobs\Bitrix\SyncBitrixTask;
use App\Models\ActivityLog;
use App\Models\BitrixOutboundOp;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Step 3.5: our own changes come back as events; they must be recognised,
 * never loop, and lose to a real change made in Bitrix right after ours.
 */
class BitrixEchoTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Task $task;

    /** The task as the fake Bitrix holds it ([] = deleted). */
    private array $bitrixTask;

    private ?array $bitrixGroup = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actor = User::factory()->create(['bitrix_user_id' => 5]);
        $this->actor->roles()->attach(Role::where('name', 'Administrator')->first());
        $this->actor->storeBitrixToken(new BitrixToken('actor-access', 'actor-refresh', CarbonImmutable::now()->addHour()));
        app()->instance(BitrixGatewayInterface::SERVICE_ACCOUNT, $this->actor);

        $this->task = Task::forceCreate([
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'project_id' => null,
            'created_by' => $this->actor->id, 'assigned_to' => $this->actor->id, 'bitrix_task_id' => 15,
        ]);
        $this->bitrixTask = ['id' => '15', 'title' => 'T', 'status' => '2', 'priority' => '1', 'createdBy' => '5', 'responsibleId' => '5', 'groupId' => '0'];

        Http::fake(fn (Request $request) => Http::response(['result' => match (basename(parse_url($request->url(), PHP_URL_PATH), '.json')) {
            'tasks.task.get' => $this->bitrixTask === [] ? [] : ['task' => $this->bitrixTask],
            'socialnetwork.api.workgroup.list' => ['workgroups' => array_values(array_filter([$this->bitrixGroup]))],
            'sonet_group.user.get' => [['USER_ID' => '5', 'ROLE' => 'A']],
            'sonet_group.create' => 40,
            default => true,
        }]));
        Sanctum::actingAs($this->actor);
    }

    public function test_the_echo_of_our_change_is_confirmed_and_never_loops(): void
    {
        $this->putJson("/api/v1/tasks/{$this->task->id}", ['title' => 'Ours'])->assertOk();
        $this->bitrixTask['title'] = 'Ours';
        $logs = ActivityLog::count();

        dispatch_sync(new SyncBitrixTask(15));

        $this->assertSame(OutboundOpStatus::Confirmed, BitrixOutboundOp::sole()->status);
        $this->assertSame($logs, ActivityLog::count());
        // Handling the event sent nothing back to Bitrix: only our one update.
        $this->assertSame(['tasks.task.update'], $this->writes());
    }

    public function test_the_echo_of_a_due_date_is_confirmed(): void
    {
        $this->putJson("/api/v1/tasks/{$this->task->id}", ['due_date' => '2026-11-26'])->assertOk();
        // Bitrix keeps the deadline we sent, in its own ISO form.
        $sent = collect(Http::recorded())->map(fn (array $pair) => $pair[0])
            ->first(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.update.json'));
        $this->bitrixTask['deadline'] = $sent['fields']['DEADLINE'];

        dispatch_sync(new SyncBitrixTask(15));

        $this->assertSame(OutboundOpStatus::Confirmed, BitrixOutboundOp::sole()->status);
    }

    public function test_two_concurrent_edits_end_with_the_bitrix_version(): void
    {
        $this->putJson("/api/v1/tasks/{$this->task->id}", ['title' => 'Ours'])->assertOk();
        // Someone renamed it in Bitrix right after us.
        $this->bitrixTask['title'] = 'Theirs';

        dispatch_sync(new SyncBitrixTask(15));

        $this->assertSame('Theirs', $this->task->fresh()->title);
        $this->assertSame(OutboundOpStatus::Conflict, BitrixOutboundOp::sole()->status);
        $this->assertTrue(ActivityLog::where('action', 'bitrix_conflict')->where('task_id', $this->task->id)->exists());
    }

    public function test_a_custom_priority_survives_its_own_echo(): void
    {
        // urgent is ours; Bitrix only knows it as "high" (2).
        $this->putJson("/api/v1/tasks/{$this->task->id}", ['priority' => 'urgent'])->assertOk();
        $this->bitrixTask['priority'] = '2';

        dispatch_sync(new SyncBitrixTask(15));

        $this->assertSame('urgent', $this->task->fresh()->priority);
    }

    public function test_a_custom_priority_survives_a_later_unrelated_bitrix_change(): void
    {
        $this->task->forceFill(['priority' => 'urgent'])->save();
        $this->bitrixTask = [...$this->bitrixTask, 'priority' => '2', 'title' => 'Renamed in Bitrix'];

        dispatch_sync(new SyncBitrixTask(15));

        $task = $this->task->fresh();
        $this->assertSame(['Renamed in Bitrix', 'urgent'], [$task->title, $task->priority]);
    }

    public function test_an_old_change_of_ours_is_not_taken_for_an_echo(): void
    {
        $this->putJson("/api/v1/tasks/{$this->task->id}", ['title' => 'Ours'])->assertOk();
        $this->travel(BitrixOutboundOp::WINDOW_SECONDS + 1)->seconds();
        $this->bitrixTask['title'] = 'Later Bitrix edit';

        dispatch_sync(new SyncBitrixTask(15));

        $this->assertSame('Later Bitrix edit', $this->task->fresh()->title);
        $this->assertSame(OutboundOpStatus::Sent, BitrixOutboundOp::sole()->status);
        $this->assertFalse(ActivityLog::where('action', 'bitrix_conflict')->exists());
    }

    public function test_the_echo_of_our_delete_is_confirmed(): void
    {
        $this->deleteJson("/api/v1/tasks/{$this->task->id}")->assertOk();
        $this->bitrixTask = [];

        dispatch_sync(new SyncBitrixTask(15));

        $this->assertSame(OutboundOpStatus::Confirmed, BitrixOutboundOp::sole()->status);
    }

    public function test_the_echo_of_deleting_a_project_is_confirmed_and_it_stays_deleted(): void
    {
        $project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 40]);
        $this->deleteJson("/api/v1/projects/{$project->id}")->assertOk();
        $this->bitrixGroup = ['id' => '40', 'name' => 'P', 'ownerId' => '5', 'closed' => 'Y'];

        dispatch_sync(new SyncBitrixProject(40));

        $this->assertSame(OutboundOpStatus::Confirmed, BitrixOutboundOp::where('entity', 'project')->sole()->status);
        $this->assertSoftDeleted($project);
    }

    public function test_the_echo_of_a_new_project_is_confirmed(): void
    {
        $this->postJson('/api/v1/projects', ['title' => 'Launch', 'start_date' => '2026-12-01'])->assertCreated();
        // Bitrix keeps the day as portal-midnight and returns it in UTC.
        $this->bitrixGroup = ['id' => '40', 'name' => 'Launch', 'description' => '', 'ownerId' => '5', 'closed' => 'N', 'projectDateStart' => '2026-11-30T21:00:00+00:00'];

        dispatch_sync(new SyncBitrixProject(40));

        $this->assertSame(OutboundOpStatus::Confirmed, BitrixOutboundOp::where('entity', 'project')->sole()->status);
    }

    public function test_two_quick_changes_of_ours_are_both_echoes_not_a_conflict(): void
    {
        $this->putJson("/api/v1/tasks/{$this->task->id}", ['title' => 'First'])->assertOk();
        $this->putJson("/api/v1/tasks/{$this->task->id}", ['title' => 'Second'])->assertOk();
        // By the time the first event is handled, Bitrix already has the second change.
        $this->bitrixTask['title'] = 'Second';

        dispatch_sync(new SyncBitrixTask(15));
        dispatch_sync(new SyncBitrixTask(15));

        $this->assertSame([OutboundOpStatus::Confirmed, OutboundOpStatus::Confirmed], BitrixOutboundOp::orderBy('id')->pluck('status')->all());
        $this->assertFalse(ActivityLog::where('action', 'bitrix_conflict')->exists());
        $this->assertSame('Second', $this->task->fresh()->title);
    }

    public function test_an_event_answering_our_older_change_leaves_the_newer_one_waiting(): void
    {
        $this->putJson("/api/v1/tasks/{$this->task->id}", ['title' => 'First'])->assertOk();
        $this->putJson("/api/v1/tasks/{$this->task->id}", ['title' => 'Second'])->assertOk();
        // Fetched before our second change reached Bitrix.
        $this->bitrixTask['title'] = 'First';

        dispatch_sync(new SyncBitrixTask(15));

        $this->assertSame([OutboundOpStatus::Confirmed, OutboundOpStatus::Sent], BitrixOutboundOp::orderBy('id')->pluck('status')->all());
        $this->assertSame('Second', $this->task->fresh()->title);
    }

    /** Bitrix methods called that change something (not get/list). */
    private function writes(): array
    {
        return Http::recorded()
            ->map(fn (array $pair): string => basename(parse_url($pair[0]->url(), PHP_URL_PATH), '.json'))
            ->reject(fn (string $method): bool => str_ends_with($method, '.get') || str_ends_with($method, '.list'))
            ->values()->all();
    }
}
