<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Task\TaskFiltersDTO;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TaskRepositoryInterface
{
    /**
     * The task list with its filters and sort.
     *
     * @param  User|null  $visibleTo  Only tasks this user may see; null for everything (administrators).
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginate(?User $visibleTo, TaskFiltersDTO $filters, int $perPage): LengthAwarePaginator;

    /**
     * A project's tasks, newest first, with their assignees.
     *
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginateForProject(Project $project, int $perPage): LengthAwarePaginator;

    /** Everything the task page shows. */
    public function loadForDetail(Task $task, User $viewer): Task;

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

    /** An accomplice or auditor of the task. */
    public function hasMember(Task $task, User $user): bool;

    /** Re-reads the task's columns from the database. */
    public function reload(Task $task): void;

    public function findByBitrixId(int $bitrixTaskId, bool $withTrashed = false): ?Task;

    public function idByBitrixId(int $bitrixTaskId): ?int;

    /** The Bitrix ID of a task, deleted ones too. */
    public function bitrixTaskIdOf(int $taskId): ?int;

    public function restore(Task $task): void;

    /** Notes the sync time without touching updated_at or the activity log. */
    public function markSynced(Task $task): void;

    /** Step 4.7: remembers the task's chat in Bitrix, without touching updated_at or the activity log. */
    public function setBitrixChatId(Task $task, int $chatId): void;

    /**
     * Accomplices and auditors as sorted Bitrix user IDs.
     *
     * @return array{accomplice: list<int>, auditor: list<int>}
     */
    public function memberBitrixIds(Task $task): array;

    /**
     * The Bitrix IDs of all our linked tasks.
     *
     * @return list<int>
     */
    public function allBitrixIds(): array;

    /**
     * The Bitrix IDs of our deleted tasks.
     *
     * @return list<int>
     */
    public function trashedBitrixIds(): array;

    /**
     * Deletes our copies of these Bitrix tasks.
     *
     * @param  list<int>  $bitrixTaskIds
     * @return int How many were deleted.
     */
    public function deleteByBitrixIds(array $bitrixTaskIds): int;

    /** How many of our tasks are in Bitrix. */
    public function countInBitrix(): int;

    /** @param  callable(Collection<int, Task>): void  $callback */
    public function chunkInBitrix(int $size, callable $callback): void;
}
