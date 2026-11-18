<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\Bitrix24\BitrixTaskPusher;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Tasks. Bitrix-synced writes go to Bitrix first (with the employee's
 * token) and are saved here only when Bitrix accepts them; a refusal throws
 * a BitrixException, rendered in bootstrap/app.php.
 */
class TaskService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly BitrixTaskPusher $bitrix,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function list(User $viewer, array $filters, int $perPage): LengthAwarePaginator
    {
        // Administrators see every task; everyone else what they may see.
        return $this->tasks->paginate($viewer->hasRole('Administrator') ? null : $viewer, $filters, $perPage);
    }

    public function listForProject(Project $project, int $perPage): LengthAwarePaginator
    {
        return $this->tasks->paginateForProject($project, $perPage);
    }

    public function show(User $viewer, Task $task): Task
    {
        return $this->tasks->loadForDetail($task, $viewer);
    }

    /**
     * A task in a project, or (no project) a Bitrix personal task (GROUP_ID 0).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, ?Project $project, array $data): Task
    {
        $task = new Task($data);
        $task->project_id = $project?->id;
        $task->created_by = $actor->id;
        // Step 4.4: tasks made here track time from the start.
        $task->allow_time_tracking ??= true;

        $this->bitrix->create($actor, $task);
        $this->tasks->save($task);

        return $this->tasks->loadForDetail($task, $actor);
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $actor, Task $task, array $data): Task
    {
        $task->fill($data);
        $this->bitrix->update($actor, $task);
        $this->tasks->save($task);

        return $this->tasks->freshForDetail($task, $actor);
    }

    public function changeStatus(User $actor, Task $task, string $status): Task
    {
        $task->status = $status;
        $this->bitrix->update($actor, $task);
        $this->tasks->save($task);

        return $this->tasks->freshForDetail($task, $actor);
    }

    public function delete(User $actor, Task $task): void
    {
        $this->bitrix->delete($actor, $task);
        $this->tasks->delete($task);
    }
}
