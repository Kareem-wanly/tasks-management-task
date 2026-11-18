<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Task\TaskHistoryDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixTaskHistoryReader;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.8: a task's change history, read from Bitrix24 each time it is
 * opened (no copy is kept here: only Bitrix writes it).
 */
class TaskHistoryService
{
    public function __construct(private readonly BitrixTaskHistoryReader $bitrix) {}

    public function forTask(Task $task): TaskHistoryDTO
    {
        if (! $this->bitrix->handles($task)) {
            return new TaskHistoryDTO([], TaskHistoryDTO::NOT_IN_BITRIX);
        }

        try {
            return new TaskHistoryDTO($this->bitrix->entries($task));
        } catch (BitrixException $e) {
            Log::warning('Bitrix24 task history not available.', ['task_id' => $task->id, 'error' => $e->errorCode]);

            return new TaskHistoryDTO([], $e->errorCode === BitrixException::UNREACHABLE ? TaskHistoryDTO::UNREACHABLE : TaskHistoryDTO::UNAVAILABLE);
        }
    }
}
