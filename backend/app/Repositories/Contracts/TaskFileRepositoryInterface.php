<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Task;
use App\Models\TaskFile;
use Illuminate\Support\Collection;

/**
 * Step 4.5: files attached to tasks.
 */
interface TaskFileRepositoryInterface
{
    /** Newest first, with their uploaders. */
    public function forTask(Task $task): Collection;

    /**
     * The task's files that came from Bitrix.
     *
     * @return Collection<int, TaskFile> By attached object ID.
     */
    public function bitrixFiles(Task $task): Collection;

    public function save(TaskFile $file): void;

    /** A file kept here, for a task outside Bitrix. */
    public function createLocal(Task $task, int $userId, string $name, int $size, ?string $mime, string $path): TaskFile;

    public function delete(TaskFile $file): void;

    /**
     * Deletes the task's Bitrix files not in the list.
     *
     * @param  list<int>  $attachedObjectIds
     */
    public function deleteBitrixFilesExcept(Task $task, array $attachedObjectIds): void;
}
