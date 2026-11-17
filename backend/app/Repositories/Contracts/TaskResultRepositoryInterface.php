<?php

namespace App\Repositories\Contracts;

use App\Models\Task;
use App\Models\TaskResult;
use Illuminate\Support\Collection;

/**
 * Step 4.6: tasks' official results.
 */
interface TaskResultRepositoryInterface
{
    /** Newest first, with their authors. */
    public function forTask(Task $task): Collection;

    /**
     * The task's results that came from Bitrix.
     *
     * @return Collection<int, TaskResult> By Bitrix result ID.
     */
    public function bitrixResults(Task $task): Collection;

    public function save(TaskResult $result): void;

    public function create(Task $task, int $authorId, string $text): TaskResult;

    public function update(TaskResult $result, string $text): void;

    public function delete(TaskResult $result): void;

    /**
     * Deletes the task's Bitrix results not in the list.
     *
     * @param  list<int>  $bitrixResultIds
     */
    public function deleteBitrixResultsExcept(Task $task, array $bitrixResultIds): void;
}
