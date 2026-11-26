<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Step 4.4: makes our copy of a task's time log match Bitrix's
 * (task.elapseditem.getlist rows). Bitrix sends no events for the time
 * log, so this runs when the task is opened and every night.
 */
final readonly class BitrixTimeSync
{
    public function __construct(
        private TimeEntryRepositoryInterface $entries,
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function sync(Task $task, array $rows): void
    {
        $ours = $this->entries->bitrixEntries($task);
        $users = $this->users->idsByBitrixIds(array_map(fn (array $r): int => (int) ($r['USER_ID'] ?? 0), $rows));
        $seen = [];

        foreach ($rows as $row) {
            $bitrixId = (int) $row['ID'];
            $seen[] = $bitrixId;

            $item = $ours->get($bitrixId) ?? new TaskElapsedItem(['task_id' => $task->id, 'bitrix_item_id' => $bitrixId]);
            $item->fill([
                'user_id' => $users[(int) ($row['USER_ID'] ?? 0)] ?? null,
                'seconds' => (int) ($row['SECONDS'] ?? 0),
                'comment' => ($row['COMMENT_TEXT'] ?? '') !== '' ? (string) $row['COMMENT_TEXT'] : null,
                // DATE_START/DATE_STOP come back shifted by the portal's offset; CREATED_DATE is right.
                'created_date' => isset($row['CREATED_DATE']) ? \Illuminate\Support\Facades\Date::parse($row['CREATED_DATE'])->setTimezone(config('app.timezone')) : null,
            ]);

            if (! $item->exists || $item->isDirty()) {
                $this->entries->save($item);
            }
        }

        $this->entries->deleteBitrixEntriesExcept($task, $seen);
        $this->entries->updateTaskTotal($task);
    }
}
