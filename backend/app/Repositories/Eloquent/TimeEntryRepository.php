<?php

namespace App\Repositories\Eloquent;

use App\Models\RunningTimer;
use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Models\User;
use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use Illuminate\Support\Collection;

class TimeEntryRepository implements TimeEntryRepositoryInterface
{
    public function forTask(Task $task): Collection
    {
        return $task->elapsedItems()->with(['user', 'task'])->orderByDesc('created_date')->orderByDesc('id')->get();
    }

    public function create(Task $task, int $userId, int $seconds, ?string $comment): TaskElapsedItem
    {
        return $task->elapsedItems()->create(['user_id' => $userId, 'seconds' => $seconds, 'comment' => $comment, 'created_date' => now()]);
    }

    public function update(TaskElapsedItem $entry, array $attributes): void
    {
        $entry->update($attributes);
    }

    public function delete(TaskElapsedItem $entry): void
    {
        $entry->delete();
    }

    public function updateTaskTotal(Task $task): void
    {
        $total = (int) $task->elapsedItems()->sum('seconds');

        if ($task->time_spent !== $total) {
            Task::withoutTimestamps(fn () => $task->forceFill(['time_spent' => $total])->saveQuietly());
        }
    }

    public function runningTimer(User $user): ?RunningTimer
    {
        return RunningTimer::with('task:id,title')->where('user_id', $user->id)->first();
    }

    public function startTimer(User $user, Task $task): RunningTimer
    {
        return RunningTimer::create(['user_id' => $user->id, 'task_id' => $task->id, 'started_at' => now()]);
    }

    public function deleteTimer(RunningTimer $timer): void
    {
        $timer->delete();
    }
}
