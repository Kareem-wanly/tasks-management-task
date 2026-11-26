<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Mappers\BitrixHistoryMapper;
use App\Models\Task;

/**
 * Step 4.8: a task's change history as Bitrix24 records it
 * (tasks.task.history.list). Read only: Bitrix writes it itself for every
 * change, under the employee who made it, including the ones we send.
 */
class BitrixTaskHistoryReader
{
    public function __construct(
        private readonly BitrixGatewayInterface $bitrix,
        private readonly BitrixTaskPusher $tasks,
        private readonly BitrixHistoryMapper $mapper,
    ) {}

    /** Whether the task lives in Bitrix (so it has a history there). */
    public function handles(Task $task): bool
    {
        return $task->bitrix_task_id !== null && $this->tasks->syncs($task);
    }

    /**
     * Newest first. Read with the service account: who may see it is decided
     * by our own rules (whoever sees the task).
     *
     * @return list<array{id: int, field: string, from: ?string, to: ?string, user: array{id: ?int, name: string}|null, created_at: ?string}>
     */
    public function entries(Task $task): array
    {
        $rows = $this->bitrix->callAsService('tasks.task.history.list', ['taskId' => $task->bitrix_task_id])['result']['list'] ?? [];

        return $this->mapper->entries(is_array($rows) ? array_values($rows) : []);
    }
}
