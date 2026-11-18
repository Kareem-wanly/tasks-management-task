<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tasks.view');
    }

    private function canSee(User $user, Task $task): bool
    {
        return $task->isVisibleTo($user);
    }

    private function isAssignee(User $user, Task $task): bool
    {
        return $task->assigned_to === $user->id;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.view') && $this->canSee($user, $task);
    }

    public function create(User $user, ?Project $project = null): bool
    {
        if (! $user->hasPermission('tasks.create')) {
            return false;
        }

        if (! $project) {
            return true;
        }

        return $project->hasMember($user);
    }

    public function update(User $user, Task $task): bool
    {
        if (! $this->canSee($user, $task)) {
            return false;
        }

        return $task->project?->owner_id === $user->id
            || $task->created_by === $user->id
            || $user->hasPermission('tasks.update');
    }

    public function updateStatus(User $user, Task $task): bool
    {
        if (! $this->canSee($user, $task)) {
            return false;
        }

        return $task->project?->owner_id === $user->id
            || $user->hasPermission('tasks.update')
            || ($user->hasPermission('tasks.change_status') && (int) $task->assigned_to === (int) $user->id);
    }

    public function delete(User $user, Task $task): bool
    {
        if (! $this->canSee($user, $task)) {
            return false;
        }

        return $task->project?->owner_id === $user->id
            || $task->created_by === $user->id
            || $user->hasPermission('tasks.delete');
    }

    public function assign(User $user, Task $task, ?Project $project = null): bool
    {
        $targetProject = $task->project ?? $project;

        return $targetProject?->owner_id === $user->id
            || $user->hasPermission('tasks.assign');
    }
}
