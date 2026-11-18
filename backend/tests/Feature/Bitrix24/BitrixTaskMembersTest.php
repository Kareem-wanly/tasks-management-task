<?php

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixToken;
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
 * Step 4.1: accomplices and auditors, watching and delegating.
 */
class BitrixTaskMembersTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    /** The task's responsible person; a plain member. */
    private User $member;

    /** Not in the project. */
    private User $outsider;

    private Project $project;

    private Task $task;

    /** The task as the fake Bitrix holds it. */
    private array $bitrixTask;

    /** Access token => Bitrix user ID, for the watch/delegate fakes. */
    private const TOKENS = ['actor-access' => '5', 'member-access' => '7', 'outsider-access' => '8'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actor = $this->bitrixUser(5, 'actor', 'Administrator');
        $this->member = $this->bitrixUser(7, 'member', 'Member');
        $this->outsider = $this->bitrixUser(8, 'outsider', 'Member');
        app()->instance(BitrixClient::SERVICE_ACCOUNT, $this->actor);

        $this->project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 3]);
        $this->project->members()->attach($this->member->id, ['role' => 'member']);

        $this->task = Task::forceCreate([
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'project_id' => $this->project->id,
            'created_by' => $this->actor->id, 'assigned_to' => $this->member->id, 'bitrix_task_id' => 15,
        ]);
        $this->bitrixTask = ['id' => '15', 'title' => 'T', 'status' => '2', 'priority' => '1', 'createdBy' => '5',
            'responsibleId' => '7', 'groupId' => '3', 'accomplices' => [], 'auditors' => []];

        Http::fake(fn (Request $request) => Http::response(['result' => $this->bitrix($request)]));
    }

    private function bitrixUser(int $bitrixId, string $name, string $role): User
    {
        $user = User::factory()->create(['bitrix_user_id' => $bitrixId, 'name' => ucfirst($name)]);
        $user->roles()->attach(Role::where('name', $role)->first());
        $user->storeBitrixToken(new BitrixToken("{$name}-access", "{$name}-refresh", CarbonImmutable::now()->addHour()));

        return $user;
    }

    /** A small Bitrix: keeps the member lists the way the real portal does. */
    private function bitrix(Request $request): mixed
    {
        $method = basename(parse_url($request->url(), PHP_URL_PATH), '.json');
        $caller = self::TOKENS[$request['auth'] ?? ''] ?? null;
        $task = &$this->bitrixTask;

        match ($method) {
            'tasks.task.update' => $task = [
                ...$task,
                'accomplices' => array_map('strval', $request['fields']['ACCOMPLICES'] ?? $task['accomplices']),
                'auditors' => array_map('strval', $request['fields']['AUDITORS'] ?? $task['auditors']),
            ],
            'tasks.task.startwatch' => $task['auditors'] = array_values(array_unique([...$task['auditors'], $caller])),
            'tasks.task.stopwatch' => $task['auditors'] = array_values(array_diff($task['auditors'], [$caller])),
            // The delegator keeps following the task.
            'tasks.task.delegate' => $task = [
                ...$task,
                'responsibleId' => (string) $request['userId'],
                'auditors' => array_values(array_unique([...$task['auditors'], $task['responsibleId']])),
            ],
            default => null,
        };

        return match ($method) {
            'tasks.task.get' => ['task' => $this->bitrixTask],
            'user.get' => [],
            default => true,
        };
    }

    /** @return list<int> Our user IDs in one role, sorted. */
    private function people(string $role): array
    {
        return $this->task->fresh()->{$role}()->orderBy('users.id')->pluck('users.id')->all();
    }

    public function test_members_are_sent_as_the_employee_and_saved_as_bitrix_has_them(): void
    {
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/tasks/{$this->task->id}/members", [
            'accomplice_ids' => [$this->outsider->id],
            'auditor_ids' => [$this->member->id, $this->outsider->id],
        ])->assertOk()
            ->assertJsonPath('data.accomplices.0.id', $this->outsider->id)
            ->assertJsonCount(2, 'data.auditors');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'tasks.task.update.json')
            && $r['auth'] === 'actor-access'
            && $r['fields'] === ['ACCOMPLICES' => [8], 'AUDITORS' => [7, 8]]);
        $this->assertSame([$this->outsider->id], $this->people('accomplices'));
        $this->assertSame([$this->member->id, $this->outsider->id], $this->people('auditors'));
    }

    public function test_empty_lists_clear_them_in_bitrix(): void
    {
        $this->task->auditors()->sync([$this->outsider->id]);
        $this->bitrixTask['auditors'] = ['8'];
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/tasks/{$this->task->id}/members", ['accomplice_ids' => [], 'auditor_ids' => []])->assertOk();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'tasks.task.update.json') && $r['fields']['AUDITORS'] === []);
        $this->assertSame([], $this->people('auditors'));
    }

    public function test_someone_outside_bitrix_cannot_be_added(): void
    {
        $local = User::factory()->create(['bitrix_user_id' => null]);
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/tasks/{$this->task->id}/members", ['accomplice_ids' => [], 'auditor_ids' => [$local->id]])
            ->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertSame([], $this->people('auditors'));
    }

    public function test_watching_and_unwatching(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson("/api/tasks/{$this->task->id}/watch")->assertOk();
        $this->assertSame([$this->member->id], $this->people('auditors'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'tasks.task.startwatch.json') && $r['auth'] === 'member-access');

        $this->deleteJson("/api/tasks/{$this->task->id}/watch")->assertOk();
        $this->assertSame([], $this->people('auditors'));
    }

    public function test_the_responsible_person_delegates_and_keeps_following(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson("/api/tasks/{$this->task->id}/delegate", ['user_id' => $this->outsider->id])->assertOk();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'tasks.task.delegate.json')
            && $r['auth'] === 'member-access' && $r['userId'] === 8);
        $this->assertSame($this->outsider->id, $this->task->fresh()->assigned_to);
        $this->assertSame([$this->member->id], $this->people('auditors'));
    }

    public function test_only_the_responsible_person_delegates(): void
    {
        // Sees the task (an auditor) but is not responsible for it.
        $this->task->auditors()->sync([$this->outsider->id]);
        Sanctum::actingAs($this->outsider);

        $this->postJson("/api/tasks/{$this->task->id}/delegate", ['user_id' => $this->actor->id])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_accomplices_and_auditors_see_the_task_outside_its_project(): void
    {
        Sanctum::actingAs($this->outsider);
        $this->getJson("/api/tasks/{$this->task->id}")->assertForbidden();

        $this->task->auditors()->sync([$this->outsider->id]);

        $this->getJson("/api/tasks/{$this->task->id}")->assertOk();
        $this->getJson('/api/tasks')->assertJsonPath('data.0.id', $this->task->id);
    }

    public function test_members_changed_in_bitrix_arrive_with_the_event(): void
    {
        $this->bitrixTask = [...$this->bitrixTask, 'accomplices' => ['8'], 'auditors' => ['5', '99']];

        SyncBitrixTask::dispatchSync(15);

        $this->assertSame([$this->outsider->id], $this->people('accomplices'));
        // 99 is not a Bitrix user we could import: left out.
        $this->assertSame([$this->actor->id], $this->people('auditors'));
    }

    public function test_the_echo_of_our_member_change_is_confirmed(): void
    {
        Sanctum::actingAs($this->actor);
        $this->putJson("/api/tasks/{$this->task->id}/members", ['accomplice_ids' => [], 'auditor_ids' => [$this->outsider->id]])->assertOk();

        SyncBitrixTask::dispatchSync(15);

        $this->assertSame(BitrixOutboundOp::CONFIRMED, BitrixOutboundOp::sole()->status);
        $this->assertFalse(ActivityLog::where('action', 'bitrix_conflict')->exists());
    }

    public function test_a_member_change_in_bitrix_right_after_ours_is_a_conflict_bitrix_wins(): void
    {
        Sanctum::actingAs($this->actor);
        $this->putJson("/api/tasks/{$this->task->id}/members", ['accomplice_ids' => [], 'auditor_ids' => [$this->outsider->id]])->assertOk();
        $this->bitrixTask['auditors'] = ['8', '5'];

        SyncBitrixTask::dispatchSync(15);

        $this->assertSame(BitrixOutboundOp::CONFLICT, BitrixOutboundOp::sole()->status);
        $this->assertSame([$this->actor->id, $this->outsider->id], $this->people('auditors'));
    }

    public function test_a_task_outside_bitrix_changes_here_only(): void
    {
        $local = Project::forceCreate(['title' => 'Local', 'status' => 'active', 'owner_id' => $this->actor->id]);
        $this->task->forceFill(['project_id' => $local->id, 'bitrix_task_id' => null])->save();
        Sanctum::actingAs($this->actor);

        $this->putJson("/api/tasks/{$this->task->id}/members", ['accomplice_ids' => [$this->member->id], 'auditor_ids' => []])->assertOk();

        Http::assertNothingSent();
        $this->assertSame([$this->member->id], $this->people('accomplices'));
    }
}
