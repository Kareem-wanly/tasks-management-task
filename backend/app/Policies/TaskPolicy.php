<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Models\TaskElapsedItem;
use App\Models\TaskResult;
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

    /**
     * Step 4.3: whoever may edit the task, plus the people working on it
     * (responsible person and accomplices). Bitrix has the last word.
     */
    public function updateChecklist(User $user, Task $task): bool
    {
        return $this->update($user, $task)
            || ($this->canSee($user, $task) && ($this->isAssignee($user, $task) || $task->accomplices()->whereKey($user->id)->exists()));
    }

    /** Step 4.6: the same people as for the checklist add results. */
    public function addResult(User $user, Task $task): bool
    {
        return $this->updateChecklist($user, $task);
    }

    /** Step 4.6: as in Bitrix, a result is changed only by its author (administrators pass every policy). */
    public function manageResult(User $user, Task $task, TaskResult $result): bool
    {
        return $this->canSee($user, $task) && $result->author_id === $user->id;
    }

    /** Step 4.7: whoever sees the task reads its chat (it replaced comments, so their permissions apply). */
    public function viewChat(User $user, Task $task): bool
    {
        return $this->canSee($user, $task) && $user->hasPermission('comments.view');
    }

    public function postInChat(User $user, Task $task): bool
    {
        return $this->canSee($user, $task) && $user->hasPermission('comments.create');
    }

    /**
     * Step 4.7: a message is changed or deleted only by its author. Administrators pass every
     * policy, so requests also check TaskChatMessage::isChangeableBy().
     */
    public function manageChatMessage(User $user, Task $task, TaskChatMessage $message): bool
    {
        return $this->canSee($user, $task) && $message->isChangeableBy($user);
    }

    /** Step 4.5: the same people as for the checklist attach and remove files. */
    public function manageFiles(User $user, Task $task): bool
    {
        return $this->updateChecklist($user, $task);
    }

    /** Step 4.4: the same people as for the checklist log time on a task. */
    public function logTime(User $user, Task $task): bool
    {
        return $this->updateChecklist($user, $task);
    }

    /** Step 4.4: everyone changes only their own entries (administrators pass every policy). */
    public function manageTimeEntry(User $user, Task $task, TaskElapsedItem $entry): bool
    {
        return $this->canSee($user, $task) && $entry->user_id === $user->id;
    }

    /** Step 4.1: as in Bitrix, only the responsible person delegates. */
    public function delegate(User $user, Task $task): bool
    {
        return $this->canSee($user, $task) && $this->isAssignee($user, $task);
    }

    public function assign(User $user, Task $task, ?Project $project = null): bool
    {
        $targetProject = $task->project ?? $project;

        return $targetProject?->owner_id === $user->id
            || $user->hasPermission('tasks.assign');
    }
}
