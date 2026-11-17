<?php

namespace App\Integrations\Bitrix24;

use App\Models\Task;
use App\Models\TaskResult;
use App\Repositories\Contracts\TaskResultRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\Carbon;

/**
 * Step 4.6: makes our copy of a task's results match Bitrix's
 * (tasks.task.result.list items, REST 3.0). Bitrix sends no events for
 * results, so this runs when the task is opened and every night.
 */
class BitrixResultSync
{
    public function __construct(
        private readonly TaskResultRepositoryInterface $results,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function sync(Task $task, array $items): void
    {
        $ours = $this->results->bitrixResults($task);
        $authors = $this->users->idsByBitrixIds(array_map(fn ($i) => (int) ($i['authorId'] ?? 0), $items));
        $seen = [];

        foreach ($items as $item) {
            $bitrixId = (int) $item['id'];
            $seen[] = $bitrixId;

            $result = $ours->get($bitrixId) ?? new TaskResult(['task_id' => $task->id, 'bitrix_result_id' => $bitrixId]);
            $result->fill([
                'author_id' => $authors[(int) ($item['authorId'] ?? 0)] ?? null,
                'text' => (string) ($item['text'] ?? ''),
                'status' => (string) ($item['status'] ?? 'open'),
            ]);
            // Bitrix's own times, so the list reads the same in both places.
            $result->created_at = $this->time($item['createdAt'] ?? null) ?? $result->created_at;
            $result->updated_at = $this->time($item['updatedAt'] ?? null) ?? $result->updated_at;

            if (! $result->exists || $result->isDirty()) {
                $result->timestamps = false;
                $this->results->save($result);
            }
        }

        $this->results->deleteBitrixResultsExcept($task, $seen);
    }

    private function time(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->setTimezone(config('app.timezone')) : null;
    }
}
