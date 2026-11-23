<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Task;
use App\Services\Contracts\ActivityLogServiceInterface;
use Illuminate\Support\Facades\Auth;

final readonly class TaskObserver
{
    public function __construct(private ActivityLogServiceInterface $activities) {}

    public function created(Task $task): void
    {
        $this->activities->logTaskCreated($task, Auth::user());
    }

    public function updated(Task $task): void
    {
        $this->activities->logTaskUpdated($task, Auth::user());
    }

    public function deleted(Task $task): void
    {
        $this->activities->logTaskDeleted($task, Auth::user());
    }
}
