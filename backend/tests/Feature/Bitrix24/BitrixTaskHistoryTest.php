<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixToken;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Step 4.8: a task's change history, read from Bitrix24.
 */
class BitrixTaskHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Task $task;

    /** What tasks.task.history.list answers; an array of error fields to fail. */
    private array $answer = [];

    private bool $unreachable = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actor = User::factory()->create(['bitrix_user_id' => 5, 'name' => 'Rama']);
        $this->actor->roles()->attach(Role::where('name', 'Administrator')->first());
        $this->actor->storeBitrixToken(new BitrixToken('actor-access', 'actor-refresh', CarbonImmutable::now()->addHour()));
        User::factory()->create(['bitrix_user_id' => 6, 'name' => 'Karim']);
        app()->instance(BitrixClient::SERVICE_ACCOUNT, $this->actor);

        TaskStatus::where('key', 'todo')->update(['bitrix_id' => 2]);
        TaskStatus::where('key', 'in_progress')->update(['bitrix_id' => 3]);
        TaskPriority::where('key', 'medium')->update(['bitrix_id' => 1]);
        TaskPriority::where('key', 'high')->update(['bitrix_id' => 2]);

        $project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 3]);
        $this->task = Task::forceCreate([
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'project_id' => $project->id,
            'created_by' => $this->actor->id, 'assigned_to' => $this->actor->id, 'bitrix_task_id' => 23,
        ]);

        Http::fake(function (Request $request) {
            if ($this->unreachable) {
                throw new ConnectionException('timed out');
            }

            return isset($this->answer['error'])
                ? Http::response($this->answer, 400)
                : Http::response(['result' => ['list' => $this->answer]]);
        });
        Sanctum::actingAs($this->actor);
    }

    public function test_the_history_comes_from_bitrix_newest_first_in_our_terms(): void
    {
        $this->answer = [
            $this->row(124, 'NEW', null, null, '2026-11-15T12:31:57+00:00'),
            $this->row(125, 'RESPONSIBLE_ID', '5', '6', '2026-11-15T12:32:22+00:00'),
            $this->row(140, 'PRIORITY', '1', '2', '2026-11-15T13:10:01+00:00'),
            $this->row(146, 'STATUS', '2', '3', '2026-11-15T13:12:41+00:00', 6),
            $this->row(147, 'DEADLINE', '1795413600', '1795305600', '2026-11-15T13:12:51+00:00'),
            $this->row(150, 'TITLE', 'Ours', 'Theirs', '2026-11-15T13:13:31+00:00', 99),
        ];

        $this->getJson("/api/tasks/{$this->task->id}/history")
            ->assertOk()
            ->assertJsonPath('meta.unavailable_because', null)
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.field', 'TITLE')
            // Someone we don't have: Bitrix's own name.
            ->assertJsonPath('data.0.user', ['id' => null, 'name' => 'Bitrix Name'])
            ->assertJsonPath('data.1.from', '2026-11-23T06:00:00+00:00')
            ->assertJsonPath('data.2.from', 'todo')
            ->assertJsonPath('data.2.to', 'in_progress')
            ->assertJsonPath('data.2.user.name', 'Karim')
            ->assertJsonPath('data.3.to', 'high')
            ->assertJsonPath('data.4.from', 'Rama')
            ->assertJsonPath('data.4.to', 'Karim')
            ->assertJsonPath('data.5.field', 'NEW');

        // Read with the service account, never written to.
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.history.list.json') && $r['taskId'] === 23 && $r['auth'] === 'actor-access');
        Http::assertSentCount(1);
    }

    public function test_a_task_outside_bitrix_has_no_history_there(): void
    {
        $this->task->project->forceFill(['bitrix_group_id' => null])->save();

        $this->getJson("/api/tasks/{$this->task->id}/history")->assertOk()->assertJsonPath('meta.unavailable_because', 'not_in_bitrix');

        Http::assertNothingSent();
    }

    /** A task deleted in Bitrix (not yet removed here) answers ACCESS_DENIED. */
    public function test_a_task_bitrix_refuses_shows_as_unavailable(): void
    {
        $this->answer = ['error' => 'ACCESS_DENIED', 'error_description' => 'Access denied.'];

        $this->getJson("/api/tasks/{$this->task->id}/history")->assertOk()->assertJsonPath('meta.unavailable_because', 'unavailable')->assertJsonCount(0, 'data');
    }

    public function test_bitrix_unreachable_says_so(): void
    {
        $this->unreachable = true;

        $this->getJson("/api/tasks/{$this->task->id}/history")->assertOk()->assertJsonPath('meta.unavailable_because', 'unreachable');
    }

    public function test_only_people_who_see_the_task_read_its_history(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/tasks/{$this->task->id}/history")->assertForbidden();

        Http::assertNothingSent();
    }

    private function row(int $id, string $field, mixed $from, mixed $to, string $date, int $userId = 5): array
    {
        return ['id' => $id, 'createdDate' => $date, 'field' => $field, 'value' => ['from' => $from, 'to' => $to],
            'user' => ['id' => $userId, 'name' => 'Bitrix', 'lastName' => 'Name', 'secondName' => '', 'login' => 'x']];
    }
}
