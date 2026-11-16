<?php

namespace App\Integrations\Bitrix24;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;

/**
 * Step 4.3: checklist changes, sent with the acting employee's token. After
 * each call the checklist is fetched back, so ours always matches Bitrix
 * (it renumbers SORT_INDEX itself). Only for tasks it handles();
 * TaskChecklistService keeps the others' checklists here.
 */
class BitrixChecklistPusher
{
    public function __construct(
        private readonly BitrixClient $bitrix,
        private readonly BitrixTaskPusher $tasks,
        private readonly BitrixChecklistSync $sync,
    ) {}

    /** Whether the task lives in Bitrix. */
    public function handles(Task $task): bool
    {
        return $task->bitrix_task_id !== null && $this->tasks->syncs($task);
    }

    /** Brings our copy of the task's checklist up to date. */
    public function refresh(Task $task): void
    {
        $rows = $this->bitrix->callAsService('task.checklistitem.getlist', [
            'TASKID' => $task->bitrix_task_id,
            'ORDER' => ['SORT_INDEX' => 'asc'],
        ])['result'] ?? [];
        $this->sync->sync($task, is_array($rows) ? $rows : []);
    }

    /** Adds to the given list; without one, Bitrix adds to its first list (creating it when there is none). */
    public function add(User $actor, Task $task, string $title, ?TaskChecklistItem $list): void
    {
        $fields = ['TITLE' => $title];
        if ($list?->bitrix_item_id) {
            $fields['PARENT_ID'] = $list->bitrix_item_id;
        }

        $this->call($actor, $task, 'task.checklistitem.add', ['FIELDS' => $fields]);
    }

    public function rename(User $actor, TaskChecklistItem $item, string $title): void
    {
        $this->call($actor, $item->task, 'task.checklistitem.update', ['ITEMID' => $item->bitrix_item_id, 'FIELDS' => ['TITLE' => $title]]);
    }

    public function toggle(User $actor, TaskChecklistItem $item, bool $complete): void
    {
        $this->call($actor, $item->task, $complete ? 'task.checklistitem.complete' : 'task.checklistitem.renew', ['ITEMID' => $item->bitrix_item_id]);
    }

    public function delete(User $actor, TaskChecklistItem $item): void
    {
        $this->call($actor, $item->task, 'task.checklistitem.delete', ['ITEMID' => $item->bitrix_item_id]);
    }

    /**
     * Puts the item right after $after, or first when $after is null.
     * Bitrix only moves "after an item" (after the list itself would take
     * the item out of it), so first place is two moves: after the current
     * $first item, then that one after it.
     */
    public function move(User $actor, TaskChecklistItem $item, ?TaskChecklistItem $after, ?TaskChecklistItem $first): void
    {
        $task = $item->task;

        if ($after !== null) {
            $this->call($actor, $task, 'task.checklistitem.moveafteritem', ['ITEMID' => $item->bitrix_item_id, 'AFTERITEMID' => $after->bitrix_item_id], refresh: false);
        } elseif ($first && $first->id !== $item->id) {
            $this->call($actor, $task, 'task.checklistitem.moveafteritem', ['ITEMID' => $item->bitrix_item_id, 'AFTERITEMID' => $first->bitrix_item_id], refresh: false);
            $this->call($actor, $task, 'task.checklistitem.moveafteritem', ['ITEMID' => $first->bitrix_item_id, 'AFTERITEMID' => $item->bitrix_item_id], refresh: false);
        }

        $this->refresh($task);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function call(User $actor, Task $task, string $method, array $params, bool $refresh = true): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }

        $this->bitrix->callAsUser($actor, $method, ['TASKID' => $task->bitrix_task_id, ...$params]);

        if ($refresh) {
            $this->refresh($task);
        }
    }
}
