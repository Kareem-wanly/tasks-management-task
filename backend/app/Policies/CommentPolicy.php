<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;

class CommentPolicy
{
    private function isProjectMember(User $user, ?Task $task): bool
    {
        $project = $task?->project;

        return $project ? $project->hasMember($user) : false;
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
        if (! $this->isProjectMember($user, $comment->task)) {
            return false;
        }

        if (! $user->hasPermission('comments.update')) {
            return false;
        }

        return $this->isOwner($user, $comment) || $user->hasPermission('comments.manage_all');
    }

    public function delete(User $user, Comment $comment): bool
    {
        if (! $this->isProjectMember($user, $comment->task)) {
            return false;
        }

        if (! $user->hasPermission('comments.delete')) {
            return false;
        }

        return $this->isOwner($user, $comment) || $user->hasPermission('comments.manage_all');
    }

    public function viewAny(User $user, ?Task $task = null): bool
    {
        if (! $user->hasPermission('comments.view')) {
            return false;
        }

        if (! $task) {
            return true;
        }

        return $this->isProjectMember($user, $task);
    }
}
