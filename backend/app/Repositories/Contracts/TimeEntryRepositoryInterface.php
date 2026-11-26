<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\RunningTimer;
use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Step 4.4: a task's time log, and each employee's running timer.
 */
interface TimeEntryRepositoryInterface
{
    /**
     * Newest first, with their authors (and task, for permission checks).
     *
     * @return Collection<int, TaskElapsedItem>
     */
    public function forTask(Task $task): Collection;

    public function create(Task $task, int $userId, int $seconds, ?string $comment): TaskElapsedItem;

    /** @param  array<string, mixed>  $attributes */
    public function update(TaskElapsedItem $entry, array $attributes): void;

    public function delete(TaskElapsedItem $entry): void;

    /** Sets the task's time_spent to the sum of its log, without touching updated_at. */
    public function updateTaskTotal(Task $task): void;

    /** The user's running timer, with its task (id, title). */
    public function runningTimer(User $user): ?RunningTimer;

    public function startTimer(User $user, Task $task): RunningTimer;

    public function deleteTimer(RunningTimer $timer): void;

    /**
     * Deletes the timer row in one statement, so of two requests stopping the
     * same timer only one gets it.
     *
     * @return bool Whether this call deleted it.
     */
    public function claimTimer(RunningTimer $timer): bool;

    /** Puts a claimed timer back as it was (same task and start time). */
    public function restoreTimer(RunningTimer $timer): void;

    /**
     * The task's entries that came from Bitrix.
     *
     * @return Collection<int, TaskElapsedItem> By Bitrix item ID.
     */
    public function bitrixEntries(Task $task): Collection;

    public function save(TaskElapsedItem $entry): void;

    /**
     * Deletes the task's Bitrix entries not in the list.
     *
     * @param  list<int>  $bitrixItemIds
     */
    public function deleteBitrixEntriesExcept(Task $task, array $bitrixItemIds): void;
}
