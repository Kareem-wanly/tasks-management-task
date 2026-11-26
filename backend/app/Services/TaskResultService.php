<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Bitrix\SyncBitrixResultsAction;
use App\DTOs\Task\TaskResultDTO;
use App\DTOs\Task\TaskResultsDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixResultRepositoryInterface;
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
        private BitrixResultRepositoryInterface $bitrixResults,
        private SyncBitrixResultsAction $resultSync,
    ) {}

    /** The results, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskResultsDTO
    {
        $stale = false;

        if ($task->isInBitrix()) {
            try {
                $this->refreshFromBitrix($task);
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

        if ($task->isInBitrix()) {
            $this->ensureSignedInWithBitrix($actor);
            $this->bitrixResults->add($actor, $task->bitrix_task_id, $text);
            $this->refreshFromBitrix($task);
        } else {
            $this->results->create($task, $actor->id, $text);
        }

        return $this->current($task);
    }

    public function update(User $actor, TaskResult $result, TaskResultDTO $change): TaskResultsDTO
    {
        $text = $change->text;
        $task = $result->task;

        // Decided by the result itself: one made here before the task was linked stays here.
        if ($result->bitrix_result_id !== null) {
            // Bitrix lets only the result's author change it.
            $this->ensureSignedInWithBitrix($actor);
            $this->bitrixResults->update($actor, $result->bitrix_result_id, $text);
            $this->refreshFromBitrix($task);
        } else {
            $this->results->update($result, $text);
        }

        return $this->current($task);
    }

    public function delete(User $actor, TaskResult $result): TaskResultsDTO
    {
        $task = $result->task;

        if ($result->bitrix_result_id !== null) {
            $this->ensureSignedInWithBitrix($actor);
            $this->bitrixResults->delete($actor, $result->bitrix_result_id);
            $this->refreshFromBitrix($task);
        } else {
            $this->results->delete($result);
        }

        return $this->current($task);
    }

    /** Brings our copy of the task's results up to date (also the nightly import). */
    public function refreshFromBitrix(Task $task): void
    {
        $this->resultSync->handle($task, $this->bitrixResults->list((int) $task->bitrix_task_id));
    }

    private function current(Task $task, bool $stale = false): TaskResultsDTO
    {
        return new TaskResultsDTO($task, $this->results->forTask($task), $stale);
    }

    /** Changes go to Bitrix with the employee's own token. */
    private function ensureSignedInWithBitrix(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }
}
