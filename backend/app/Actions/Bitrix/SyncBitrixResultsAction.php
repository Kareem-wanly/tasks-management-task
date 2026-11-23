<?php

declare(strict_types=1);

namespace App\Actions\Bitrix;

use App\Integrations\Bitrix24\BitrixPayload;
use App\Models\Task;
use App\Models\TaskResult;
use App\Repositories\Contracts\TaskResultRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Step 4.6: makes our copy of a task's results match Bitrix's
 * (tasks.task.result.list items, REST 3.0). Bitrix sends no events for
 * results, so this runs when the task is opened and every night.
 */
final readonly class SyncBitrixResultsAction
{
    public function __construct(
        private TaskResultRepositoryInterface $results,
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(Task $task, array $items): void
    {
        $ours = $this->results->bitrixResults($task);
        $items = array_map(fn (array $item): BitrixPayload => BitrixPayload::of($item, 'tasks.task.result.list'), $items);
        $authors = $this->users->idsByBitrixIds(array_map(fn (BitrixPayload $i): int => $i->intOrNull('authorId') ?? 0, $items));
        $seen = [];

        foreach ($items as $item) {
            $bitrixId = $item->int('id');
            $seen[] = $bitrixId;

            $result = $ours->get($bitrixId) ?? new TaskResult(['task_id' => $task->id, 'bitrix_result_id' => $bitrixId]);
            $result->fill([
                'author_id' => $authors[$item->intOrNull('authorId') ?? 0] ?? null,
                'text' => $item->stringOrNull('text') ?? '',
                'status' => $item->stringOrNull('status') ?? 'open',
            ]);
            // Bitrix's own times, so the list reads the same in both places.
            $result->created_at = $item->localDateOrNull('createdAt') ?? $result->created_at;
            $result->updated_at = $item->localDateOrNull('updatedAt') ?? $result->updated_at;

            if (! $result->exists || $result->isDirty()) {
                $result->timestamps = false;
                $this->results->save($result);
            }
        }

        $this->results->deleteBitrixResultsExcept($task, $seen);
    }
}
