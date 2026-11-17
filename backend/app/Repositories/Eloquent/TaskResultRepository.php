<?php

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Models\TaskResult;
use App\Repositories\Contracts\TaskResultRepositoryInterface;
use Illuminate\Support\Collection;

class TaskResultRepository implements TaskResultRepositoryInterface
{
    public function forTask(Task $task): Collection
    {
        return $task->results()->with(['author:id,name,avatar_url', 'task'])->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    public function bitrixResults(Task $task): Collection
    {
        return $task->results()->whereNotNull('bitrix_result_id')->get()->keyBy('bitrix_result_id');
    }

    public function save(TaskResult $result): void
    {
        $result->save();
    }

    public function create(Task $task, int $authorId, string $text): TaskResult
    {
        return $task->results()->create(['author_id' => $authorId, 'text' => $text]);
    }

    public function update(TaskResult $result, string $text): void
    {
        $result->update(['text' => $text]);
    }

    public function delete(TaskResult $result): void
    {
        $result->delete();
    }

    public function deleteBitrixResultsExcept(Task $task, array $bitrixResultIds): void
    {
        $task->results()->whereNotNull('bitrix_result_id')->whereNotIn('bitrix_result_id', $bitrixResultIds ?: [0])->delete();
    }
}
