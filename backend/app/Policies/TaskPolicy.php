<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Administrator') || $user->hasPermission('projects.update')) {
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
        if (!$this->isProjectMember($user, $task)) {
            return false;
        }

        return $task->project->owner_id === $user->id 
            || $task->created_by === $user->id 
            || $user->hasPermission('tasks.manage');
    }

    public function updateStatus(User $user, Task $task): bool
    {
        if (!$this->isProjectMember($user, $task)) {
            return false;
        }

        return $this->isAssignee($user, $task) 
            || $task->project->owner_id === $user->id 
            || $user->hasPermission('tasks.change_status');
    }

    public function delete(User $user, Task $task): bool
    {
        if (!$this->isProjectMember($user, $task)) {
            return false;
        }

        return $task->project->owner_id === $user->id 
            || $task->created_by === $user->id 
            || $user->hasPermission('tasks.delete');
    }
}