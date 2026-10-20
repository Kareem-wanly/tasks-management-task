<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // #4. Allow administrators to perform any action without further checks
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tasks.view');
    }

    private function isProjectMember(User $user, Task $task): bool
    {
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

    public function create(User $user): bool
    {
        return $user->hasPermission('tasks.create');
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

        return $task->project->owner_id === $user->id
            || $task->project->members()->where('users.id', $user->id)->exists();
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
}
