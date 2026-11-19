<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Enums\OutboundOpStatus;
use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixToken;
use App\Jobs\Bitrix\SyncBitrixTask;
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
 * Step 4.2: subtasks (Bitrix PARENT_ID).
 */
class BitrixSubtasksTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Project $project;

    private Task $parent;

    /** What tasks.task.get answers. */
    private array $bitrixTask = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actor = User::factory()->create(['bitrix_user_id' => 5]);
        $this->actor->roles()->attach(Role::where('name', 'Administrator')->first());
        $this->actor->storeBitrixToken(new BitrixToken('actor-access', 'actor-refresh', CarbonImmutable::now()->addHour()));
        app()->instance(BitrixClient::SERVICE_ACCOUNT, $this->actor);

        $this->project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 3]);
        $this->parent = $this->task(['title' => 'Parent', 'bitrix_task_id' => 50]);

        Http::fake(fn (Request $request) => Http::response(['result' => match (basename(parse_url($request->url(), PHP_URL_PATH), '.json')) {
            'tasks.task.add' => ['task' => ['id' => '51', 'status' => '2']],
            'tasks.task.get' => ['task' => $this->bitrixTask],
            default => true,
        }]));
        Sanctum::actingAs($this->actor);
    }

    public function test_a_subtask_is_created_under_its_parent_in_bitrix(): void
    {
        $this->postJson("/api/v1/projects/{$this->project->id}/tasks", ['title' => 'Child', 'parent_id' => $this->parent->id])
            ->assertCreated();

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.add.json')
            && $r['fields']['PARENT_ID'] === 50 && $r['fields']['GROUP_ID'] === 3);
        $this->assertSame($this->parent->id, Task::where('bitrix_task_id', 51)->sole()->parent_id);
    }

    public function test_a_plain_task_has_no_parent_in_bitrix(): void
    {
        $this->postJson("/api/v1/projects/{$this->project->id}/tasks", ['title' => 'Alone'])->assertCreated();

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.add.json') && $r['fields']['PARENT_ID'] === 0);
    }

    public function test_taking_a_subtask_out_of_its_parent_sends_zero(): void
    {
        $child = $this->task(['bitrix_task_id' => 51, 'parent_id' => $this->parent->id]);

        $this->putJson("/api/v1/tasks/{$child->id}", ['parent_id' => null])->assertOk();

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.update.json') && $r['fields'] === ['PARENT_ID' => 0]);
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_a_task_cannot_go_under_its_own_subtask(): void
    {
        $child = $this->task(['bitrix_task_id' => 51, 'parent_id' => $this->parent->id]);

        $this->putJson("/api/v1/tasks/{$this->parent->id}", ['parent_id' => $child->id])->assertJsonValidationErrors('parent_id');
        $this->putJson("/api/v1/tasks/{$this->parent->id}", ['parent_id' => $this->parent->id])->assertJsonValidationErrors('parent_id');

        Http::assertNothingSent();
    }

    public function test_the_parent_must_be_a_task_the_user_sees(): void
    {
        $member = User::factory()->create(['bitrix_user_id' => 7]);
        $member->roles()->attach(Role::where('name', 'Member')->first());
        $member->storeBitrixToken(new BitrixToken('member-access', 'member-refresh', CarbonImmutable::now()->addHour()));
        $mine = Task::forceCreate(['title' => 'Mine', 'status' => 'todo', 'priority' => 'medium', 'project_id' => null,
            'created_by' => $member->id, 'assigned_to' => $member->id, 'bitrix_task_id' => 60]);
        Sanctum::actingAs($member);

        // Not in the project, so the parent is hidden from them.
        $this->putJson("/api/v1/tasks/{$mine->id}", ['parent_id' => $this->parent->id])->assertJsonValidationErrors('parent_id');
    }

    public function test_the_task_page_shows_its_parent_and_subtasks(): void
    {
        $child = $this->task(['title' => 'Child', 'bitrix_task_id' => 51, 'parent_id' => $this->parent->id]);

        $this->getJson("/api/v1/tasks/{$this->parent->id}")
            ->assertJsonPath('data.subtasks.0.id', $child->id)
            ->assertJsonPath('data.parent', null);
        $this->getJson("/api/v1/tasks/{$child->id}")
            ->assertJsonPath('data.parent', ['id' => $this->parent->id, 'title' => 'Parent'])
            ->assertJsonCount(0, 'data.subtasks');
    }

    public function test_a_subtask_made_in_bitrix_arrives_under_its_parent(): void
    {
        $this->bitrixTask = $this->bitrixRow(51, 50);

        dispatch_sync(new SyncBitrixTask(51));

        $this->assertSame($this->parent->id, Task::where('bitrix_task_id', 51)->sole()->parent_id);
    }

    public function test_a_subtask_whose_parent_is_unknown_is_kept_without_it(): void
    {
        $this->bitrixTask = $this->bitrixRow(51, 999);

        dispatch_sync(new SyncBitrixTask(51));

        $this->assertNull(Task::where('bitrix_task_id', 51)->sole()->parent_id);
    }

    public function test_the_echo_of_a_new_subtask_is_confirmed(): void
    {
        $this->postJson("/api/v1/projects/{$this->project->id}/tasks", ['title' => 'Child', 'parent_id' => $this->parent->id])
            ->assertCreated();
        $this->bitrixTask = $this->bitrixRow(51, 50);

        dispatch_sync(new SyncBitrixTask(51));

        $this->assertSame(OutboundOpStatus::Confirmed, BitrixOutboundOp::sole()->status);
    }

    private function task(array $attributes = []): Task
    {
        return Task::forceCreate([
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'project_id' => $this->project->id,
            'created_by' => $this->actor->id, 'assigned_to' => $this->actor->id, ...$attributes,
        ]);
    }

    private function bitrixRow(int $id, int $parentId): array
    {
        return ['id' => (string) $id, 'title' => 'Child', 'status' => '2', 'priority' => '1', 'createdBy' => '5',
            'responsibleId' => '5', 'groupId' => '3', 'parentId' => (string) $parentId, 'accomplices' => [], 'auditors' => [], 'allowTimeTracking' => 'Y'];
    }
}
