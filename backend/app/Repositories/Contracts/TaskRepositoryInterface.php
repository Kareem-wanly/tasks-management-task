<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TaskRepositoryInterface
{
    /**
     * The task list with its filters and sort.
     *
     * @param  User|null  $visibleTo  Only tasks this user may see; null for everything (administrators).
     * @param  array<string, mixed>  $filters  See ListTasksRequest::filters().
     */
    public function paginate(?User $visibleTo, array $filters, int $perPage): LengthAwarePaginator;

    /** A project's tasks, newest first, with their assignees. */
    public function paginateForProject(Project $project, int $perPage): LengthAwarePaginator;

    /** Everything the task page shows, plus comments when asked. */
    public function loadForDetail(Task $task, User $viewer, bool $withComments = false): Task;

    /** A fresh copy with what the task page shows, as write responses return it. */
    public function freshForDetail(Task $task, User $viewer): Task;

    public function save(Task $task): void;

    public function delete(Task $task): void;

    /** @param  list<int>  $userIds */
    public function syncAccomplices(Task $task, array $userIds): void;

    /** @param  list<int>  $userIds */
    public function syncAuditors(Task $task, array $userIds): void;

    public function addAuditor(Task $task, int $userId): void;

    public function removeAuditor(Task $task, int $userId): void;

    public function find(int $id): ?Task;
}
