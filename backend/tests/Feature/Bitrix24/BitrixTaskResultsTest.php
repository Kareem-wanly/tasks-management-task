<?php

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixToken;
use App\Jobs\Bitrix\ImportBitrixResults;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskResult;
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
 * Step 4.6: task results, through Bitrix24 REST 3.0.
 */
class BitrixTaskResultsTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Task $task;

    /** @var array<int, array<string, mixed>> The fake Bitrix results, by ID. */
    private array $results = [];

    private int $nextId = 1;

    /** null, 'unreachable' or 'refuse'. */
    private ?string $failure = null;

    /** Access token => Bitrix user ID. */
    private const TOKENS = ['actor-access' => 5, 'assignee-access' => 7, 'auditor-access' => 8];

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actor = $this->user(5, 'actor', Role::where('name', 'Administrator')->first());
        app()->instance(BitrixClient::SERVICE_ACCOUNT, $this->actor);

        $project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 3]);
        $this->task = Task::forceCreate([
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'project_id' => $project->id,
            'created_by' => $this->actor->id, 'assigned_to' => $this->actor->id, 'bitrix_task_id' => 15,
        ]);

        Http::fake(function (Request $request) {
            if ($this->failure === 'unreachable') {
                throw new ConnectionException('timed out');
            }
            if ($this->failure === 'refuse') {
                return Http::response(['error' => ['code' => 'BITRIX_REST_V3_EXCEPTION_ACCESSDENIEDEXCEPTION', 'message' => 'Access denied.']], 403);
            }

            return Http::response(['result' => $this->bitrix(basename(parse_url($request->url(), PHP_URL_PATH)), $request->data())]);
        });
        Sanctum::actingAs($this->actor);
    }

    private function user(int $bitrixId, string $name, Role $role): User
    {
        $user = User::factory()->create(['bitrix_user_id' => $bitrixId]);
        $user->roles()->attach($role);
        $user->storeBitrixToken(new BitrixToken("{$name}-access", "{$name}-refresh", CarbonImmutable::now()->addHour()));

        return $user;
    }

    /** REST 3.0 answers with result.item / result.items. */
    private function bitrix(string $method, array $data): mixed
    {
        $id = (int) ($data['id'] ?? 0);

        return match ($method) {
            'tasks.task.result.list' => ['items' => array_values(array_filter($this->results, fn ($r) => $r['taskId'] === $data['filter'][0][1]))],
            'tasks.task.result.add' => ['item' => $this->bitrixResult($data['fields']['text'], self::TOKENS[$data['auth']])],
            'tasks.task.result.update' => ['item' => $this->results[$id] = [...$this->results[$id], 'text' => $data['fields']['text']]],
            'tasks.task.result.delete' => (function () use ($id) {
                unset($this->results[$id]);

                return true;
            })(),
            default => true,
        };
    }

    private function bitrixResult(string $text, int $authorId): array
    {
        $id = $this->nextId++;

        return $this->results[$id] = ['id' => $id, 'taskId' => 15, 'text' => $text, 'authorId' => $authorId,
            'createdAt' => '2026-11-16T13:51:36+00:00', 'updatedAt' => '2026-11-16T13:51:36+00:00', 'status' => 'open'];
    }

    public function test_opening_the_task_brings_in_its_bitrix_results(): void
    {
        $this->bitrixResult('Shipped the release', 5);

        $this->getJson("/api/tasks/{$this->task->id}/results")
            ->assertOk()
            ->assertJsonPath('data.0.text', 'Shipped the release')
            ->assertJsonPath('data.0.author.id', $this->actor->id)
            ->assertJsonPath('data.0.created_at', '2026-11-16T13:51:36.000000Z')
            ->assertJsonPath('meta.can_add', true);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/rest/api/tasks.task.result.list') && $r['filter'] === [['taskId', 15]]);
    }

    public function test_a_result_is_added_edited_and_deleted_as_the_employee_through_rest_3(): void
    {
        $this->postJson("/api/tasks/{$this->task->id}/results", ['text' => '  Done and verified  '])
            ->assertOk()
            ->assertJsonPath('data.0.text', 'Done and verified')
            ->assertJsonPath('data.0.can_edit', true);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://portal.test/rest/api/tasks.task.result.add'
            && $r['auth'] === 'actor-access' && $r['fields'] === ['taskId' => 15, 'text' => 'Done and verified']);

        $result = TaskResult::sole();
        $this->putJson("/api/task-results/{$result->id}", ['text' => 'Done, verified, deployed'])->assertJsonPath('data.0.text', 'Done, verified, deployed');
        $this->deleteJson("/api/task-results/{$result->id}")->assertJsonCount(0, 'data');
    }

    public function test_only_the_author_changes_a_result_and_auditors_cannot_add(): void
    {
        $viewOnly = Role::create(['name' => 'Viewer', 'display_name' => 'Viewer', 'description' => 'x']);
        $viewOnly->permissions()->sync(Permission::where('name', 'tasks.view')->pluck('id'));
        $assignee = $this->user(7, 'assignee', $viewOnly);
        $auditor = $this->user(8, 'auditor', $viewOnly);
        $this->task->forceFill(['assigned_to' => $assignee->id])->save();
        $this->task->auditors()->sync([$auditor->id]);
        $this->bitrixResult('By the actor', 5);
        $this->getJson("/api/tasks/{$this->task->id}/results");
        $actorsResult = TaskResult::sole();

        Sanctum::actingAs($auditor);
        $this->getJson("/api/tasks/{$this->task->id}/results")->assertJsonPath('meta.can_add', false);
        $this->postJson("/api/tasks/{$this->task->id}/results", ['text' => 'Nope'])->assertForbidden();

        Sanctum::actingAs($assignee);
        $this->postJson("/api/tasks/{$this->task->id}/results", ['text' => 'Mine'])->assertOk();
        $this->putJson("/api/task-results/{$actorsResult->id}", ['text' => 'Hijack'])->assertForbidden();
    }

    public function test_a_bitrix_refusal_saves_nothing_and_says_why(): void
    {
        $this->failure = 'refuse';

        $this->postJson("/api/tasks/{$this->task->id}/results", ['text' => 'Done'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Bitrix24 does not allow you to do this.');

        $this->assertSame(0, TaskResult::count());
    }

    public function test_bitrix_unreachable_shows_our_last_copy(): void
    {
        $this->bitrixResult('Kept', 5);
        $this->getJson("/api/tasks/{$this->task->id}/results");
        $this->failure = 'unreachable';

        $this->getJson("/api/tasks/{$this->task->id}/results")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.stale', true);
    }

    public function test_a_task_outside_bitrix_keeps_its_results_here(): void
    {
        $local = Project::forceCreate(['title' => 'Local', 'status' => 'active', 'owner_id' => $this->actor->id]);
        $this->task->forceFill(['project_id' => $local->id, 'bitrix_task_id' => null])->save();

        $this->postJson("/api/tasks/{$this->task->id}/results", ['text' => 'Local result'])->assertOk()->assertJsonPath('data.0.text', 'Local result');

        Http::assertNothingSent();
    }

    public function test_the_nightly_pass_brings_in_every_tasks_results(): void
    {
        $this->bitrixResult('From Bitrix', 5);

        ImportBitrixResults::dispatchSync();

        $this->assertSame(['From Bitrix'], TaskResult::pluck('text')->all());
    }
}
