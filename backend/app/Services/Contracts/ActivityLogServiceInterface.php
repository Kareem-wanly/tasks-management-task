<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityLogServiceInterface
{
    /** @return LengthAwarePaginator<int, \App\Models\ActivityLog> */
    public function forProject(Project $project, int $perPage): LengthAwarePaginator;

    /**
     * Administrators see everything; others see the projects they own or belong to.
     *
     * @return LengthAwarePaginator<int, \App\Models\ActivityLog>
     */
    public function forUser(User $user, int $perPage): LengthAwarePaginator;

    /** Entries written by the observers: $actor is the signed-in user, if any. */
    public function logTaskCreated(Task $task, ?User $actor): void;

    public function logTaskUpdated(Task $task, ?User $actor): void;

    public function logTaskDeleted(Task $task, ?User $actor): void;

    public function logProjectCreated(Project $project, ?User $actor): void;

    public function logProjectUpdated(Project $project, ?User $actor): void;

    public function logProjectDeleted(Project $project, ?User $actor): void;

    /** Entries written by ProjectService when $actor changes the project's members. */
    public function logMemberAdded(User $actor, Project $project, User $member): void;

    public function logMemberRemoved(User $actor, Project $project, User $member): void;
}
