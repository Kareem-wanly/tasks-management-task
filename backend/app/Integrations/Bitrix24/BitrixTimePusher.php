<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Models\User;

/**
 * Step 4.4: time log changes, sent with the acting employee's token, so
 * each entry is in their name. After each call the log is fetched back.
 * Only for tasks it handles(); TaskTimeService keeps the others' log here.
 */
class BitrixTimePusher
{
    public function __construct(
        private readonly BitrixClient $bitrix,
        private readonly BitrixTaskPusher $tasks,
        private readonly BitrixTimeSync $sync,
    ) {}

    /** Whether the task lives in Bitrix. */
    public function handles(Task $task): bool
    {
        return $task->bitrix_task_id !== null && $this->tasks->syncs($task);
    }

    /** Brings our copy of the task's time log up to date. */
    public function refresh(Task $task): void
    {
        $rows = $this->bitrix->callAsService('task.elapseditem.getlist', ['TASKID' => $task->bitrix_task_id])['result'] ?? [];
        $this->sync->sync($task, is_array($rows) ? $rows : []);
    }

    public function add(User $actor, Task $task, int $seconds, ?string $comment): void
    {
        $this->call($actor, $task, 'task.elapseditem.add', ['ARFIELDS' => ['SECONDS' => $seconds, 'COMMENT_TEXT' => $comment ?? '']]);
    }

    public function update(User $actor, TaskElapsedItem $entry, int $seconds, ?string $comment): void
    {
        $this->call($actor, $entry->task, 'task.elapseditem.update', [
            'ITEMID' => $entry->bitrix_item_id,
            'ARFIELDS' => ['SECONDS' => $seconds, 'COMMENT_TEXT' => $comment ?? ''],
        ]);
    }

    public function delete(User $actor, TaskElapsedItem $entry): void
    {
        $this->call($actor, $entry->task, 'task.elapseditem.delete', ['ITEMID' => $entry->bitrix_item_id]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function call(User $actor, Task $task, string $method, array $params): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }

        $this->bitrix->callAsUser($actor, $method, ['TASKID' => $task->bitrix_task_id, ...$params]);
        $this->refresh($task);
    }
}
