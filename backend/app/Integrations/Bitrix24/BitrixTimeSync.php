<?php

namespace App\Integrations\Bitrix24;

use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Models\User;
use Carbon\Carbon;

/**
 * Step 4.4: makes our copy of a task's time log match Bitrix's
 * (task.elapseditem.getlist rows). Bitrix sends no events for the time
 * log, so this runs when the task is opened and every night.
 */
class BitrixTimeSync
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function sync(Task $task, array $rows): void
    {
        $ours = $task->elapsedItems()->whereNotNull('bitrix_item_id')->get()->keyBy('bitrix_item_id');
        $users = User::whereIn('bitrix_user_id', array_map(fn ($r) => (int) ($r['USER_ID'] ?? 0), $rows))->pluck('id', 'bitrix_user_id');
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
                'created_date' => isset($row['CREATED_DATE']) ? Carbon::parse($row['CREATED_DATE'])->setTimezone(config('app.timezone')) : null,
            ]);

            if (! $item->exists || $item->isDirty()) {
                $item->save();
            }
        }

        $task->elapsedItems()->whereNotNull('bitrix_item_id')->whereNotIn('bitrix_item_id', $seen ?: [0])->delete();
        $this->updateTotal($task);
    }

    /** The task's time_spent is the sum of its log (Bitrix TIME_SPENT_IN_LOGS). */
    public function updateTotal(Task $task): void
    {
        $total = (int) $task->elapsedItems()->sum('seconds');

        if ($task->time_spent !== $total) {
            Task::withoutTimestamps(fn () => $task->forceFill(['time_spent' => $total])->saveQuietly());
        }
    }
}
