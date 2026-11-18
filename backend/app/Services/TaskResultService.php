<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Task\TaskResultDTO;
use App\DTOs\Task\TaskResultsDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixResultPusher;
use App\Models\Task;
use App\Models\TaskResult;
use App\Models\User;
use App\Repositories\Contracts\TaskResultRepositoryInterface;
use App\Services\Contracts\TaskResultServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.6: a task's official results. Bitrix sends no events for results,
 * so they are fetched from Bitrix each time the task is opened. Tasks in
 * Bitrix change there first; tasks of local-only projects change here only.
 */
final readonly class TaskResultService implements TaskResultServiceInterface
{
    public function __construct(
        private TaskResultRepositoryInterface $results,
        private BitrixResultPusher $bitrix,
    ) {}

    /** The results, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskResultsDTO
    {
        $stale = false;

        if ($this->bitrix->handles($task)) {
            try {
                $this->bitrix->refresh($task);
            } catch (BitrixException $e) {
                Log::warning('Bitrix24 task results not refreshed; showing our copy.', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                $stale = true;
            }
        }

        return $this->current($task, $stale);
    }

    public function add(User $actor, Task $task, TaskResultDTO $result): TaskResultsDTO
    {
        $text = $result->text;

        $this->bitrix->handles($task)
            ? $this->bitrix->add($actor, $task, $text)
            : $this->results->create($task, $actor->id, $text);

        return $this->current($task);
    }

    public function update(User $actor, TaskResult $result, TaskResultDTO $change): TaskResultsDTO
    {
        $text = $change->text;
        $task = $result->task;

        // Decided by the result itself: one made here before the task was linked stays here.
        $result->bitrix_result_id !== null
            ? $this->bitrix->update($actor, $result, $text)
            : $this->results->update($result, $text);

        return $this->current($task);
    }

    public function delete(User $actor, TaskResult $result): TaskResultsDTO
    {
        $task = $result->task;

        $result->bitrix_result_id !== null
            ? $this->bitrix->delete($actor, $result)
            : $this->results->delete($result);

        return $this->current($task);
    }

    private function current(Task $task, bool $stale = false): TaskResultsDTO
    {
        return new TaskResultsDTO($task, $this->results->forTask($task), $stale);
    }
}
