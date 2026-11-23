<?php

declare(strict_types=1);

namespace App\Actions\Bitrix;

use App\Integrations\Bitrix24\BitrixPayload;
use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Step 4.4: makes our copy of a task's time log match Bitrix's
 * (task.elapseditem.getlist rows). Bitrix sends no events for the time
 * log, so this runs when the task is opened and every night.
 */
final readonly class SyncBitrixTimeLogAction
{
    public function __construct(
        private TimeEntryRepositoryInterface $entries,
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function handle(Task $task, array $rows): void
    {
        $ours = $this->entries->bitrixEntries($task);
        $rows = array_map(fn (array $row): BitrixPayload => BitrixPayload::of($row, 'task.elapseditem.getlist'), $rows);
        $users = $this->users->idsByBitrixIds(array_map(fn (BitrixPayload $r): int => $r->intOrNull('USER_ID') ?? 0, $rows));
        $seen = [];

        foreach ($rows as $row) {
            $bitrixId = $row->int('ID');
            $seen[] = $bitrixId;

            $item = $ours->get($bitrixId) ?? new TaskElapsedItem(['task_id' => $task->id, 'bitrix_item_id' => $bitrixId]);
            $item->fill([
                'user_id' => $users[$row->intOrNull('USER_ID') ?? 0] ?? null,
                'seconds' => $row->intOrNull('SECONDS') ?? 0,
                'comment' => ($row->stringOrNull('COMMENT_TEXT') ?? '') !== '' ? $row->stringOrNull('COMMENT_TEXT') : null,
                // DATE_START/DATE_STOP come back shifted by the portal's offset; CREATED_DATE is right.
                'created_date' => $row->localDateOrNull('CREATED_DATE'),
            ]);

            if (! $item->exists || $item->isDirty()) {
                $this->entries->save($item);
            }
        }

        $this->entries->deleteBitrixEntriesExcept($task, $seen);
        $this->entries->updateTaskTotal($task);
    }
}
