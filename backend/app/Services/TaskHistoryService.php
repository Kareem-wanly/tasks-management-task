<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Task\TaskHistoryDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixHistoryMapperInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTaskRepositoryInterface;
use App\Models\Task;
use App\Services\Contracts\TaskHistoryServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.8: a task's change history, read from Bitrix24 each time it is
 * opened (no copy is kept here: only Bitrix writes it).
 */
final readonly class TaskHistoryService implements TaskHistoryServiceInterface
{
    public function __construct(
        private BitrixTaskRepositoryInterface $bitrixTasks,
        private BitrixHistoryMapperInterface $mapper,
    ) {}

    public function forTask(Task $task): TaskHistoryDTO
    {
        if (! $task->isInBitrix()) {
            return new TaskHistoryDTO([], TaskHistoryDTO::NOT_IN_BITRIX);
        }

        try {
            // Read with the service account: who may see it is decided by our own rules (whoever sees the task).
            return new TaskHistoryDTO($this->mapper->entries($this->bitrixTasks->history($task->bitrix_task_id)));
        } catch (BitrixException $e) {
            Log::warning('Bitrix24 task history not available.', ['task_id' => $task->id, 'error' => $e->errorCode]);

            return new TaskHistoryDTO([], $e->errorCode === BitrixException::UNREACHABLE ? TaskHistoryDTO::UNREACHABLE : TaskHistoryDTO::UNAVAILABLE);
        }
    }
}
