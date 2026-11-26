<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Models\TaskFile;
use App\Repositories\Contracts\TaskFileRepositoryInterface;
use Illuminate\Support\Collection;

class TaskFileRepository implements TaskFileRepositoryInterface
{
    public function forTask(Task $task): Collection
    {
        return $task->files()->with(['uploader:id,name', 'task'])->latest()->orderByDesc('id')->get();
    }

    public function bitrixFiles(Task $task): Collection
    {
        return $task->files()->whereNotNull('attached_object_id')->get()->keyBy('attached_object_id');
    }

    public function save(TaskFile $file): void
    {
        $file->save();
    }

    public function createLocal(Task $task, int $userId, string $name, int $size, ?string $mime, string $path): TaskFile
    {
        return $task->files()->create(['uploaded_by' => $userId, 'name' => $name, 'size' => $size, 'mime' => $mime, 'path' => $path]);
    }

    public function delete(TaskFile $file): void
    {
        $file->delete();
    }

    public function deleteBitrixFilesExcept(Task $task, array $attachedObjectIds): void
    {
        $task->files()->whereNotNull('attached_object_id')->whereNotIn('attached_object_id', $attachedObjectIds ?: [0])->delete();
    }
}
