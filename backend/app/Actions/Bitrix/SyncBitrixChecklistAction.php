<?php

declare(strict_types=1);

namespace App\Actions\Bitrix;

use App\Integrations\Bitrix24\BitrixPayload;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Repositories\Contracts\TaskChecklistRepositoryInterface;

/**
 * Step 4.3: makes our copy of a task's checklists match Bitrix's
 * (task.checklistitem.getlist rows). Bitrix sends no events for
 * checklists, so this runs when the task is opened and every night.
 */
final readonly class SyncBitrixChecklistAction
{
    /** Fields task.checklistitem.getlist must return for this sync. */
    public const SELECT = ['ID', 'PARENT_ID', 'TITLE', 'SORT_INDEX', 'IS_COMPLETE'];

    public function __construct(private TaskChecklistRepositoryInterface $checklists) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function handle(Task $task, array $rows): void
    {
        $ours = $this->checklists->bitrixItems($task);
        $byBitrixId = [];

        // Every row first, then the links: an item may come before its parent
        // (Bitrix nests items under items too).
        $rows = array_map(fn (array $row): BitrixPayload => BitrixPayload::of($row, 'task.checklistitem.getlist'), $rows);

        foreach ($rows as $row) {
            $bitrixId = $row->int('ID');
            $item = $ours->get($bitrixId) ?? new TaskChecklistItem(['task_id' => $task->id, 'bitrix_item_id' => $bitrixId]);
            $item->fill([
                'title' => $row->stringOrNull('TITLE') ?? '',
                'is_complete' => $row->equals('IS_COMPLETE', 'Y'),
                'sort_index' => $row->intOrNull('SORT_INDEX') ?? 0,
            ]);
            $byBitrixId[$bitrixId] = $item;
        }

        foreach ($byBitrixId as $item) {
            if (! $item->exists || $item->isDirty()) {
                $this->checklists->save($item);
            }
        }

        foreach ($rows as $row) {
            $item = $byBitrixId[$row->int('ID')];
            $parentBitrixId = $row->intOrNull('PARENT_ID') ?? 0;
            $item->parent_id = $parentBitrixId === 0 ? null : ($byBitrixId[$parentBitrixId] ?? null)?->id;

            if ($item->isDirty()) {
                $this->checklists->save($item);
            }
        }

        $this->checklists->deleteBitrixItemsExcept($task, array_keys($byBitrixId));
    }
}
