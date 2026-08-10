<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;

class CommentPolicy
{
    
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return null;
    }

    private function isProjectMember(User $user, ?Task $task): bool
    {
        $project = $task?->project;

        if (!$project) {
            return false;
        }

        return $project->owner_id === $user->id 
            || $project->members()->where('users.id', $user->id)->exists();
    }

    private function isOwner(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    public function view(User $user, Comment $comment): bool
    {
        return $user->hasPermission('comments.view') && $this->isProjectMember($user, $comment->task);
    }

    public function create(User $user, Task $task): bool
    {
        return $user->hasPermission('comments.create') && $this->isProjectMember($user, $task);
    }

    public function update(User $user, Comment $comment): bool
    {
        if (!$this->isProjectMember($user, $comment->task)) {
            return false;
        }

        if (!$user->hasPermission('comments.update')) {
            return false;
        }

        return $this->isOwner($user, $comment) || $user->hasPermission('comments.manage_all');
    }

    public function delete(User $user, Comment $comment): bool
    {
        if (!$this->isProjectMember($user, $comment->task)) {
            return false;
        }

        if (!$user->hasPermission('comments.delete')) {
            return false;
        }

        return $this->isOwner($user, $comment) || $user->hasPermission('comments.manage_all');
    }
}