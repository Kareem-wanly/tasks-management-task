<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{

    public function create(User $user): bool
{
    return $user->hasPermission('tasks.create');
}
    
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasPermission('projects.edit')) {
            return true;
        }

        return null; 
    }

    
    private function isProjectMember(User $user, Task $task): bool
{
    return $task->project->created_by === $user->id 
        || $task->project->members()->where('users.id', $user->id)->exists();
}

    
    private function isAssignee(User $user, Task $task): bool
    {
        return $task->assigned_to === $user->id;
    }

    
    public function view(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.view') || $this->isProjectMember($user, $task);
    }

    
    public function update(User $user, Task $task): bool
    {
        if (!$user->hasPermission('tasks.edit') || !$this->isProjectMember($user, $task)) {
            return false;
        }

        return $this->isAssignee($user, $task) || $user->hasPermission('projects.edit');
    }

    
    public function delete(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.delete') 
            && $this->isProjectMember($user, $task) 
            && $user->hasPermission('projects.edit');
    }
}