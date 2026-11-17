<?php

namespace App\Services;

use App\Data\TaskResults;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixResultPusher;
use App\Models\Task;
use App\Models\TaskResult;
use App\Models\User;
use App\Repositories\Contracts\TaskResultRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.6: a task's official results. Bitrix sends no events for results,
 * so they are fetched from Bitrix each time the task is opened. Tasks in
 * Bitrix change there first; tasks of local-only projects change here only.
 */
class TaskResultService
{
    public function __construct(
        private readonly TaskResultRepositoryInterface $results,
        private readonly BitrixResultPusher $bitrix,
    ) {}

    /** The results, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskResults
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

    public function add(User $actor, Task $task, string $text): TaskResults
    {
        $this->bitrix->handles($task)
            ? $this->bitrix->add($actor, $task, $text)
            : $this->results->create($task, $actor->id, $text);

        return $this->current($task);
    }

    public function update(User $actor, TaskResult $result, string $text): TaskResults
    {
        $task = $result->task;

        $this->bitrix->handles($task)
            ? $this->bitrix->update($actor, $result, $text)
            : $this->results->update($result, $text);

        return $this->current($task);
    }

    public function delete(User $actor, TaskResult $result): TaskResults
    {
        $task = $result->task;

        $this->bitrix->handles($task)
            ? $this->bitrix->delete($actor, $result)
            : $this->results->delete($result);

        return $this->current($task);
    }

    private function current(Task $task, bool $stale = false): TaskResults
    {
        return new TaskResults($task, $this->results->forTask($task), $stale);
    }
}
