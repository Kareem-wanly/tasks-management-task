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

    private function isProjectMember(User $user, Task $task): bool
    {
        $project = $task->project;
        if (! $project) {
            return false;
        }

        return $task->project->owner_id === $user->id
            || $task->project->members()->where('users.id', $user->id)->exists();
    }

    private function isAssignee(User $user, Task $task): bool
    {
        return $task->assigned_to === $user->id;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.view') && $this->isProjectMember($user, $task);
    }

    public function create(User $user, ?Project $project = null): bool
    {
        if (! $user->hasPermission('tasks.create')) {
            return false;
        }

        if (! $project) {
            return true;
        }

        return $project->owner_id === $user->id
            || $project->members()->where('users.id', $user->id)->exists();
    }

    public function update(User $user, Task $task): bool
    {
        if (! $this->isProjectMember($user, $task)) {
            return false;
        }

        return $task->project->owner_id === $user->id
            || $task->created_by === $user->id
            || $user->hasPermission('tasks.update');
    }

    public function updateStatus(User $user, Task $task): bool
    {
        if (! $this->isProjectMember($user, $task)) {
            return false;
        }

        return $task->project?->owner_id === $user->id
            || $user->hasPermission('tasks.update')
            || ($user->hasPermission('tasks.change_status') && (int) $task->assigned_to === (int) $user->id);
    }

    public function delete(User $user, Task $task): bool
    {
        if (! $this->isProjectMember($user, $task)) {
            return false;
        }

        return $task->project->owner_id === $user->id
            || $task->created_by === $user->id
            || $user->hasPermission('tasks.delete');
    }

    public function assign(User $user, Task $task): bool
    {
        return $task->project?->owner_id === $user->id
            || $user->hasPermission('tasks.assign');
    }
}
