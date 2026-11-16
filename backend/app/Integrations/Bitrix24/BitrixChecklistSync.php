<?php

namespace App\Integrations\Bitrix24;

use App\Models\Task;
use App\Models\TaskChecklistItem;

/**
 * Step 4.3: makes our copy of a task's checklists match Bitrix's
 * (task.checklistitem.getlist rows). Bitrix sends no events for
 * checklists, so this runs when the task is opened and every night.
 */
class BitrixChecklistSync
{
    /** Fields task.checklistitem.getlist must return for this sync. */
    public const SELECT = ['ID', 'PARENT_ID', 'TITLE', 'SORT_INDEX', 'IS_COMPLETE'];

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function sync(Task $task, array $rows): void
    {
        $ours = $task->checklistItems()->whereNotNull('bitrix_item_id')->get()->keyBy('bitrix_item_id');
        $byBitrixId = [];

        // Every row first, then the links: an item may come before its parent
        // (Bitrix nests items under items too).
        foreach ($rows as $row) {
            $bitrixId = (int) $row['ID'];
            $item = $ours->get($bitrixId) ?? new TaskChecklistItem(['task_id' => $task->id, 'bitrix_item_id' => $bitrixId]);
            $item->fill([
                'title' => (string) ($row['TITLE'] ?? ''),
                'is_complete' => ($row['IS_COMPLETE'] ?? 'N') === 'Y',
                'sort_index' => (int) ($row['SORT_INDEX'] ?? 0),
            ]);
            $byBitrixId[$bitrixId] = $item;
        }

        foreach ($byBitrixId as $item) {
            if (! $item->exists || $item->isDirty()) {
                $item->save();
            }
        }

        foreach ($rows as $row) {
            $item = $byBitrixId[(int) $row['ID']];
            $parentBitrixId = (int) ($row['PARENT_ID'] ?? 0);
            $item->parent_id = $parentBitrixId === 0 ? null : ($byBitrixId[$parentBitrixId] ?? null)?->id;

            if ($item->isDirty()) {
                $item->save();
            }
        }

        $task->checklistItems()
            ->whereNotNull('bitrix_item_id')
            ->whereNotIn('bitrix_item_id', array_keys($byBitrixId) ?: [0])
            ->delete();
    }
}
