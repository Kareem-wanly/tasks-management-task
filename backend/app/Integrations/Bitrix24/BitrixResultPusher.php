<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Models\Task;
use App\Models\TaskResult;
use App\Models\User;

/**
 * Step 4.6: task results, with the employee's own token, through REST 3.0
 * (tasks.task.result.*; the classic REST has no add, and comment-based
 * results don't work on tasks that use the task chat). Needs the app's
 * "tasks" scope. After each call the list is fetched back. Only for tasks
 * it handles(); TaskResultService keeps the others' results here.
 */
class BitrixResultPusher
{
    /** tasks.task.result.list returns up to this many per call. */
    private const LIST_LIMIT = 1000;

    public function __construct(
        private readonly BitrixGatewayInterface $bitrix,
        private readonly BitrixResultSync $sync,
    ) {}

    /** Whether the task lives in Bitrix. */
    public function handles(Task $task): bool
    {
        return $task->isInBitrix();
    }

    /** Brings our copy of the task's results up to date. */
    public function refresh(Task $task): void
    {
        $items = $this->bitrix->callV3AsService('tasks.task.result.list', [
            'filter' => [['taskId', $task->bitrix_task_id]],
            'select' => ['id', 'taskId', 'text', 'authorId', 'createdAt', 'updatedAt', 'status'],
            'order' => ['createdAt' => 'DESC'],
            'pagination' => ['limit' => self::LIST_LIMIT],
        ])['result']['items'] ?? [];

        $this->sync->sync($task, is_array($items) ? $items : []);
    }

    public function add(User $actor, Task $task, string $text): void
    {
        $this->ensureLinked($actor);
        $this->bitrix->callV3AsUser($actor, 'tasks.task.result.add', ['fields' => ['taskId' => $task->bitrix_task_id, 'text' => $text]]);
        $this->refresh($task);
    }

    /** Bitrix lets only the result's author change it. */
    public function update(User $actor, TaskResult $result, string $text): void
    {
        $this->ensureLinked($actor);
        $this->bitrix->callV3AsUser($actor, 'tasks.task.result.update', ['id' => $result->bitrix_result_id, 'fields' => ['text' => $text]]);
        $this->refresh($result->task);
    }

    public function delete(User $actor, TaskResult $result): void
    {
        $this->ensureLinked($actor);
        $this->bitrix->callV3AsUser($actor, 'tasks.task.result.delete', ['id' => $result->bitrix_result_id]);
        $this->refresh($result->task);
    }

    private function ensureLinked(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }
}
