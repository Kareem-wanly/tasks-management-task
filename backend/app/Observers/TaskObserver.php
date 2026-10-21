<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;

class TaskObserver
{
    public function created(Task $task): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id() ?? $task->created_by,
            'project_id'  => $task->project_id,
            'action'      => 'task_created',
            'description' => "Task '{$task->title}' was created.",
            'metadata'    => ['task_id' => $task->id, 'title' => $task->title],
        ]);
    }

    public function updated(Task $task): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id() ?? $task->created_by,
            'project_id'  => $task->project_id,
            'action'      => 'task_updated',
            'description' => "Task '{$task->title}' was updated.",
            'metadata'    => $task->getChanges(),
        ]);
    }

    public function deleted(Task $task): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id() ?? $task->created_by,
            'project_id'  => $task->project_id,
            'action'      => 'task_deleted',
            'description' => "Task '{$task->title}' was deleted.",
            'metadata'    => ['task_id' => $task->id, 'title' => $task->title],
        ]);
    }
}