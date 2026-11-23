<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\SystemRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Services\Contracts\ActivityLogServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The activity log, written by the observers and services. The observers
 * pass the signed-in user; the entries keep their stored shape.
 */
final readonly class ActivityLogService implements ActivityLogServiceInterface
{
    public function __construct(private ActivityLogRepositoryInterface $activities) {}

    /** @return LengthAwarePaginator<int, \App\Models\ActivityLog> */
    public function forProject(Project $project, int $perPage): LengthAwarePaginator
    {
        return $this->activities->paginateForProject($project, $perPage);
    }

    /**
     * Administrators see everything; others see the projects they own or belong to.
     *
     * @return LengthAwarePaginator<int, \App\Models\ActivityLog>
     */
    public function forUser(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->activities->paginateForUser($user, ! $user->hasRole(SystemRole::Administrator), $perPage);
    }

    public function logTaskCreated(Task $task, ?User $actor): void
    {
        $this->logTask($task, $actor, ActivityAction::TaskCreated, 'created', ['task_id' => $task->id, 'title' => $task->title]);
    }

    public function logTaskUpdated(Task $task, ?User $actor): void
    {
        $this->logTask($task, $actor, ActivityAction::TaskUpdated, 'updated', $task->getChanges());
    }

    public function logTaskDeleted(Task $task, ?User $actor): void
    {
        $this->logTask($task, $actor, ActivityAction::TaskDeleted, 'deleted', ['task_id' => $task->id, 'title' => $task->title]);
    }

    public function logProjectCreated(Project $project, ?User $actor): void
    {
        $this->logProject($project, $actor, ActivityAction::ProjectCreated, 'created', ['title' => $project->title]);
    }

    public function logProjectUpdated(Project $project, ?User $actor): void
    {
        $this->logProject($project, $actor, ActivityAction::ProjectUpdated, 'updated', $project->getChanges());
    }

    public function logProjectDeleted(Project $project, ?User $actor): void
    {
        $this->logProject($project, $actor, ActivityAction::ProjectDeleted, 'deleted', null);
    }

    /**
     * Without a signed-in user (an import, a Bitrix event) the entry goes to the task's creator.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function logTask(Task $task, ?User $actor, ActivityAction $action, string $verb, array $metadata): void
    {
        $this->activities->create([
            'user_id' => $actor->id ?? $task->created_by,
            'project_id' => $task->project_id,
            'action' => $action,
            'description' => "Task '{$task->title}' was {$verb}.",
            'metadata' => $metadata,
        ]);
    }

    /**
     * Without a signed-in user the entry goes to the project's owner.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    private function logProject(Project $project, ?User $actor, ActivityAction $action, string $verb, ?array $metadata): void
    {
        $this->activities->create([
            'user_id' => $actor->id ?? $project->owner_id,
            'project_id' => $project->id,
            'action' => $action,
            'description' => "Project '{$project->title}' was {$verb}.",
            ...($metadata === null ? [] : ['metadata' => $metadata]),
        ]);
    }
}
