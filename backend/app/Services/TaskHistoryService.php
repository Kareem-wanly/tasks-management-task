<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\TaskHistory;
use App\Integrations\Bitrix24\BitrixException;
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

    public function forTask(Task $task): TaskHistory
    {
        if (! $this->bitrix->handles($task)) {
            return new TaskHistory([], TaskHistory::NOT_IN_BITRIX);
        }

        try {
            return new TaskHistory($this->bitrix->entries($task));
        } catch (BitrixException $e) {
            Log::warning('Bitrix24 task history not available.', ['task_id' => $task->id, 'error' => $e->errorCode]);

            return new TaskHistory([], $e->errorCode === BitrixException::UNREACHABLE ? TaskHistory::UNREACHABLE : TaskHistory::UNAVAILABLE);
        }
    }
}
