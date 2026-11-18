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

interface TaskServiceInterface
{
    public function list(User $viewer, TaskFiltersDTO $filters, int $perPage): LengthAwarePaginator;

    public function listForProject(Project $project, int $perPage): LengthAwarePaginator;

    public function show(User $viewer, Task $task): Task;

    /**
     * A task in a project, or (no project) a Bitrix personal task (GROUP_ID 0).
     */
    public function create(User $actor, ?Project $project, CreateTaskDTO $data): Task;

    public function update(User $actor, Task $task, UpdateTaskDTO $data): Task;

    public function changeStatus(User $actor, Task $task, ChangeTaskStatusDTO $data): Task;

    public function delete(User $actor, Task $task): void;
}
