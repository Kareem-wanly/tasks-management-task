<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Task\ChangeTaskStatusDTO;
use App\DTOs\Task\CreateTaskDTO;
use App\DTOs\Task\TaskFiltersDTO;
use App\DTOs\Task\UpdateTaskDTO;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TaskServiceInterface
{
    /** @return LengthAwarePaginator<int, Task> */
    public function list(User $viewer, TaskFiltersDTO $filters, int $perPage): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, Task> */
    public function listForProject(Project $project, int $perPage): LengthAwarePaginator;

    public function show(User $viewer, Task $task): Task;

    /** A task by its ID, without any visibility check (callers check it). */
    public function find(int $id): ?Task;

    /**
     * A task in a project, or (no project) a Bitrix personal task (GROUP_ID 0).
     */
    public function create(User $actor, ?Project $project, CreateTaskDTO $data): Task;

    public function update(User $actor, Task $task, UpdateTaskDTO $data): Task;

    public function changeStatus(User $actor, Task $task, ChangeTaskStatusDTO $data): Task;

    public function delete(User $actor, Task $task): void;

    // --- For the Bitrix24 import and sync: Bitrix is the source, so nothing here goes back to Bitrix.

    public function findByBitrixId(int $bitrixTaskId): ?Task;

    /**
     * The Bitrix IDs of all our linked tasks.
     *
     * @return list<int>
     */
    public function allBitrixIds(): array;

    /** How many of our tasks are in Bitrix. */
    public function countInBitrix(): int;

    /**
     * Our tasks that are in Bitrix, a chunk at a time.
     *
     * @param  callable(Collection<int, Task>): void  $callback
     */
    public function chunkInBitrix(int $size, callable $callback): void;

    /** Deletes our copy of a task deleted in Bitrix, here only. */
    public function removeGoneFromBitrix(Task $task): void;

    /**
     * Deletes our copies of tasks deleted in Bitrix, here only.
     *
     * @param  list<int>  $bitrixTaskIds
     * @return int How many were deleted.
     */
    public function removeAllGoneFromBitrix(array $bitrixTaskIds): int;
}
