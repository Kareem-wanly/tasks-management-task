<?php

namespace App\Services;

use App\Data\TimeLog;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixTimePusher;
use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Step 4.4: a task's time log and the employee's timer. Bitrix sends no
 * events for the time log, so it is fetched each time the task is opened.
 * Entries go to Bitrix first, in the employee's name; tasks of local-only
 * projects keep their log here only.
 *
 * The timer is ours (Bitrix has no REST method for its own): stopping it
 * logs the time. As in Bitrix, starting one stops the running one.
 */
class TaskTimeService
{
    public function __construct(
        private readonly TimeEntryRepositoryInterface $entries,
        private readonly TaskRepositoryInterface $tasks,
        private readonly BitrixTimePusher $bitrix,
    ) {}

    /** The log, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(User $viewer, Task $task): TimeLog
    {
        $stale = false;

        if ($this->bitrix->handles($task)) {
            try {
                $this->bitrix->refresh($task);
            } catch (BitrixException $e) {
                Log::warning('Bitrix24 time log not refreshed; showing our copy.', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                $stale = true;
            }
        }

        return $this->current($viewer, $task, $stale);
    }

    public function add(User $actor, Task $task, int $seconds, ?string $comment): TimeLog
    {
        $this->ensureTracking($task);
        $this->logTime($actor, $task, $seconds, $comment);

        return $this->current($actor, $task);
    }

    public function update(User $actor, TaskElapsedItem $entry, int $seconds, ?string $comment): TimeLog
    {
        $task = $entry->task;

        if ($this->bitrix->handles($task)) {
            $this->bitrix->update($actor, $entry, $seconds, $comment);
        } else {
            $this->entries->update($entry, ['seconds' => $seconds, 'comment' => $comment]);
            $this->entries->updateTaskTotal($task);
        }

        return $this->current($actor, $task);
    }

    public function delete(User $actor, TaskElapsedItem $entry): TimeLog
    {
        $task = $entry->task;

        if ($this->bitrix->handles($task)) {
            $this->bitrix->delete($actor, $entry);
        } else {
            $this->entries->delete($entry);
            $this->entries->updateTaskTotal($task);
        }

        return $this->current($actor, $task);
    }

    /** Starts the actor's timer here; a timer running on another task is stopped and logged. */
    public function startTimer(User $actor, Task $task): TimeLog
    {
        $this->ensureTracking($task);

        $running = $this->entries->runningTimer($actor);
        $stopped = null;

        if ($running?->task_id !== $task->id) {
            $stopped = $running ? $this->stopRunning($actor, null) : null;
            $this->entries->startTimer($actor, $task);
        }

        return $this->current($actor, $task, stoppedTask: $stopped);
    }

    public function stopTimer(User $actor, Task $task, ?string $comment): TimeLog
    {
        $this->stopRunning($actor, $comment);

        $this->tasks->reload($task);

        return $this->current($actor, $task);
    }

    /**
     * Logs the running timer's time and removes it. When Bitrix refuses, the
     * timer keeps running, so no time is lost and the employee can try again.
     *
     * @return Task|null The task the time went to.
     */
    private function stopRunning(User $actor, ?string $comment): ?Task
    {
        $timer = $this->entries->runningTimer($actor);
        if (! $timer) {
            return null;
        }

        $task = $this->tasks->find($timer->task_id);
        $seconds = $timer->seconds();

        // A task deleted meanwhile has nowhere to log to.
        if ($task && $seconds > 0) {
            $this->logTime($actor, $task, $seconds, $comment);
        }

        $this->entries->deleteTimer($timer);

        return $task;
    }

    private function logTime(User $actor, Task $task, int $seconds, ?string $comment): void
    {
        if ($this->bitrix->handles($task)) {
            $this->bitrix->add($actor, $task, $seconds, $comment);

            return;
        }

        $this->entries->create($task, $actor->id, $seconds, $comment);
        $this->entries->updateTaskTotal($task);
    }

    private function ensureTracking(Task $task): void
    {
        if (! $task->allow_time_tracking) {
            throw ValidationException::withMessages(['task' => 'Turn on time tracking for this task first.']);
        }
    }

    private function current(User $viewer, Task $task, bool $stale = false, ?Task $stoppedTask = null): TimeLog
    {
        return new TimeLog($task, $this->entries->forTask($task), $this->entries->runningTimer($viewer), $stale, $stoppedTask);
    }
}
