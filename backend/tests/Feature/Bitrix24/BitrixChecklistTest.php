<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Jobs\Bitrix\ImportBitrixChecklistsJob;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskChecklistItem;
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
 * Step 4.3: checklists, fetched when the task is opened, changed with the
 * employee's token.
 */
class BitrixChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Task $task;

    /** @var array<int, array<string, mixed>> The fake Bitrix checklist of task 15, by ID. */
    private array $items = [];

    private int $nextId = 100;

    private bool $unreachable = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actor = $this->user(5, 'actor', Role::where('name', 'Administrator')->first());
        app()->instance(BitrixGatewayInterface::SERVICE_ACCOUNT, $this->actor);

        $project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 3]);
        $this->task = Task::forceCreate([
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'project_id' => $project->id,
            'created_by' => $this->actor->id, 'assigned_to' => $this->actor->id, 'bitrix_task_id' => 15,
        ]);

        Http::fake(function (Request $request) {
            if ($this->unreachable) {
                throw new ConnectionException('timed out');
            }

            return Http::response(['result' => $this->bitrix(basename(parse_url($request->url(), PHP_URL_PATH), '.json'), $request->data())]);
        });
        Sanctum::actingAs($this->actor);
    }

    public function test_opening_the_task_brings_in_the_bitrix_checklist(): void
    {
        $list = $this->row('BX_CHECKLIST_1', 0);
        $this->row('Write tests', $list);
        $this->items[$this->row('Ship it', $list)]['IS_COMPLETE'] = 'Y';

        $this->getJson("/api/v1/tasks/{$this->task->id}/checklist")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Checklist 1')
            ->assertJsonPath('data.1.title', 'Write tests')
            ->assertJsonPath('data.2.is_complete', true)
            ->assertJsonPath('data.1.parent_id', $this->local('BX_CHECKLIST_1')->id)
            ->assertJsonPath('meta', ['stale' => false, 'can_edit' => true]);
    }

    public function test_an_item_deleted_in_bitrix_is_gone_on_the_next_open(): void
    {
        $list = $this->row('BX_CHECKLIST_1', 0);
        $gone = $this->row('Old', $list);
        $this->getJson("/api/v1/tasks/{$this->task->id}/checklist");

        unset($this->items[$gone]);
        $this->getJson("/api/v1/tasks/{$this->task->id}/checklist")->assertJsonCount(1, 'data');
    }

    public function test_bitrix_unreachable_shows_our_last_copy(): void
    {
        $this->row('Kept', $this->row('BX_CHECKLIST_1', 0));
        $this->getJson("/api/v1/tasks/{$this->task->id}/checklist");
        $this->unreachable = true;

        $this->getJson("/api/v1/tasks/{$this->task->id}/checklist")
            ->assertOk()
            ->assertJsonPath('data.1.title', 'Kept')
            ->assertJsonPath('meta.stale', true);
    }

    public function test_adding_ticking_renaming_and_deleting_go_to_bitrix_as_the_employee(): void
    {
        $this->postJson("/api/v1/tasks/{$this->task->id}/checklist", ['title' => 'First'])->assertOk()->assertJsonCount(2, 'data');
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'task.checklistitem.add.json') && $r['auth'] === 'actor-access' && $r['TASKID'] === 15);

        $item = $this->local('First');
        $this->patchJson("/api/v1/checklist-items/{$item->id}", ['is_complete' => true])->assertJsonPath('data.1.is_complete', true);
        $this->patchJson("/api/v1/checklist-items/{$item->id}", ['title' => 'Renamed'])->assertJsonPath('data.1.title', 'Renamed');
        $this->deleteJson("/api/v1/checklist-items/{$item->id}")->assertJsonCount(1, 'data');

        $this->assertSame([], $this->titles());
    }

    public function test_items_are_reordered_in_bitrix(): void
    {
        foreach (['A', 'B', 'C'] as $title) {
            $this->postJson("/api/v1/tasks/{$this->task->id}/checklist", ['title' => $title]);
        }

        $this->postJson("/api/v1/checklist-items/{$this->local('A')->id}/move", ['after_id' => $this->local('C')->id])->assertOk();
        $this->assertSame(['B', 'C', 'A'], $this->titles());

        // First place: Bitrix only moves "after an item", so it takes two moves.
        $this->postJson("/api/v1/checklist-items/{$this->local('A')->id}/move", ['after_id' => null])->assertOk();
        $this->assertSame(['A', 'B', 'C'], $this->titles());
    }

    public function test_an_item_moves_only_within_its_list(): void
    {
        $this->postJson("/api/v1/tasks/{$this->task->id}/checklist", ['title' => 'A']);
        $list = TaskChecklistItem::whereNull('parent_id')->sole();

        $this->postJson("/api/v1/checklist-items/{$this->local('A')->id}/move", ['after_id' => $list->id])->assertJsonValidationErrors('after_id');
        $this->patchJson("/api/v1/checklist-items/{$list->id}", ['is_complete' => true])->assertJsonValidationErrors('is_complete');
    }

    public function test_the_people_working_on_the_task_may_edit_it_but_auditors_may_not(): void
    {
        $viewOnly = Role::create(['name' => 'Viewer', 'display_name' => 'Viewer', 'description' => 'x']);
        $viewOnly->permissions()->sync(Permission::where('name', 'tasks.view')->pluck('id'));
        $assignee = $this->user(7, 'assignee', $viewOnly);
        $auditor = $this->user(8, 'auditor', $viewOnly);
        $this->task->forceFill(['assigned_to' => $assignee->id])->save();
        $this->task->project->members()->attach($assignee->id, ['role' => 'member']);
        $this->task->auditors()->sync([$auditor->id]);

        Sanctum::actingAs($auditor);
        $this->getJson("/api/v1/tasks/{$this->task->id}/checklist")->assertOk()->assertJsonPath('meta.can_edit', false);
        $this->postJson("/api/v1/tasks/{$this->task->id}/checklist", ['title' => 'Nope'])->assertForbidden();

        Sanctum::actingAs($assignee);
        $this->postJson("/api/v1/tasks/{$this->task->id}/checklist", ['title' => 'Mine'])->assertOk();
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'task.checklistitem.add.json') && $r['auth'] === 'assignee-access');
    }

    public function test_a_task_outside_bitrix_keeps_its_checklist_here(): void
    {
        $local = Project::forceCreate(['title' => 'Local', 'status' => 'active', 'owner_id' => $this->actor->id]);
        $this->task->forceFill(['project_id' => $local->id, 'bitrix_task_id' => null])->save();

        foreach (['A', 'B'] as $title) {
            $this->postJson("/api/v1/tasks/{$this->task->id}/checklist", ['title' => $title])->assertOk();
        }
        $this->postJson("/api/v1/checklist-items/{$this->local('B')->id}/move", ['after_id' => null])->assertOk();

        Http::assertNothingSent();
        $this->assertSame(['B', 'A'], $this->titles());
    }

    public function test_the_nightly_pass_brings_in_every_tasks_checklist(): void
    {
        $this->row('From Bitrix', $this->row('BX_CHECKLIST_1', 0));

        dispatch_sync(new ImportBitrixChecklistsJob);

        $this->assertSame(['From Bitrix'], $this->titles());
    }

    private function user(int $bitrixId, string $name, Role $role): User
    {
        $user = User::factory()->create(['bitrix_user_id' => $bitrixId]);
        $user->roles()->attach($role);
        $user->storeBitrixToken(new BitrixToken("{$name}-access", "{$name}-refresh", CarbonImmutable::now()->addHour()));

        return $user;
    }

    /** A small Bitrix checklist: the first item creates "BX_CHECKLIST_1", moves renumber the list. */
    private function bitrix(string $method, array $data): mixed
    {
        $id = (int) ($data['ITEMID'] ?? 0);

        switch ($method) {
            case 'task.checklistitem.getlist':
                $rows = array_values($this->items);
                usort($rows, fn (array $a, array $b): int => $a['SORT_INDEX'] <=> $b['SORT_INDEX']);

                return $rows;
            case 'task.checklistitem.add':
                $parent = $data['FIELDS']['PARENT_ID'] ?? $this->firstList() ?? $this->row('BX_CHECKLIST_1', 0);

                return $this->row($data['FIELDS']['TITLE'], $parent);
            case 'task.checklistitem.update':
                $this->items[$id]['TITLE'] = $data['FIELDS']['TITLE'];
                break;
            case 'task.checklistitem.complete':
            case 'task.checklistitem.renew':
                $this->items[$id]['IS_COMPLETE'] = $method === 'task.checklistitem.complete' ? 'Y' : 'N';
                break;
            case 'task.checklistitem.delete':
                unset($this->items[$id]);
                break;
            case 'task.checklistitem.moveafteritem':
                $this->moveAfter($id, (int) $data['AFTERITEMID']);
                break;
            case 'batch':
                return ['result' => array_map(fn ($cmd): array => array_values($this->items), $data['cmd']), 'result_error' => []];
        }

        return true;
    }

    private function row(string $title, int $parent): int
    {
        $id = $this->nextId++;
        $siblings = array_filter($this->items, fn (array $i): bool => $i['PARENT_ID'] === $parent);
        $this->items[$id] = ['ID' => (string) $id, 'PARENT_ID' => $parent, 'TITLE' => $title, 'SORT_INDEX' => count($siblings) + ($parent ? 1 : 0), 'IS_COMPLETE' => 'N'];

        return $id;
    }

    private function firstList(): ?int
    {
        $lists = array_filter($this->items, fn (array $i): bool => $i['PARENT_ID'] === 0);

        return $lists !== [] ? array_key_first($lists) : null;
    }

    private function moveAfter(int $id, int $after): void
    {
        $order = array_keys(array_filter($this->items, fn (array $i): bool => $i['PARENT_ID'] === $this->items[$id]['PARENT_ID'] && $i['ID'] !== (string) $id));
        usort($order, fn ($a, $b): int => $this->items[$a]['SORT_INDEX'] <=> $this->items[$b]['SORT_INDEX']);
        array_splice($order, array_search($after, $order, true) + 1, 0, [$id]);
        foreach ($order as $index => $itemId) {
            $this->items[$itemId]['SORT_INDEX'] = $index + 1;
        }
    }

    /** @return list<string> Item titles of the first list, in order. */
    private function titles(): array
    {
        return TaskChecklistItem::whereNotNull('parent_id')->orderBy('sort_index')->pluck('title')->all();
    }

    private function local(string $title): TaskChecklistItem
    {
        return TaskChecklistItem::where('title', $title)->sole();
    }
}
