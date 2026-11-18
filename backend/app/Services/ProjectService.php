<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Project\AddProjectMemberDTO;
use App\DTOs\Project\CreateProjectDTO;
use App\DTOs\Project\ProjectFiltersDTO;
use App\DTOs\Project\UpdateProjectDTO;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Exceptions\Project\ProjectHasUnfinishedTasksException;
use App\Exceptions\Project\ProjectMemberAlreadyAddedException;
use App\Exceptions\Project\ProjectMemberNotFoundException;
use App\Integrations\Bitrix24\BitrixProjectPusher;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\ProjectServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Projects and their members. Changes to a project linked to Bitrix24 go to
 * Bitrix first (BitrixProjectPusher) and are saved here only when Bitrix
 * accepts them.
 */
final readonly class ProjectService implements ProjectServiceInterface
{
    /** Dropdowns never list more than this many projects. */
    private const OPTIONS_LIMIT = 1000;

    public function __construct(
        private ProjectRepositoryInterface $projects,
        private UserRepositoryInterface $users,
        private ActivityLogRepositoryInterface $activities,
        private BitrixProjectPusher $bitrix,
    ) {}

    public function list(User $user, ProjectFiltersDTO $filters, int $perPage): LengthAwarePaginator
    {
        return $this->projects->paginateAccessible($user, $filters, $perPage);
    }

    public function show(Project $project): Project
    {
        return $this->projects->loadForDetail($project);
    }

    /** Forms offer active projects only; list filters ask for archived ones too. */
    public function options(User $user, bool $includeArchived): Collection
    {
        return $this->projects->optionsFor($user, $includeArchived, self::OPTIONS_LIMIT);
    }

    /**
     * A new project is a Bitrix project; its creator owns it.
     */
    public function create(User $actor, CreateProjectDTO $data): Project
    {
        $project = new Project([
            'title' => $data->title,
            'description' => $data->description,
            'start_date' => $data->startDate,
            'due_date' => $data->dueDate,
            'owner_id' => $actor->id,
            'status' => $data->status,
        ]);

        $this->bitrix->create($actor, $project);

        DB::transaction(function () use ($actor, $project): void {
            $this->projects->save($project);
            // The same role the Bitrix import gives the group owner.
            $this->projects->attachMember($project, $actor->id, ProjectMemberRole::Owner);
        });

        return $this->projects->freshWithMembers($project);
    }

    public function update(User $actor, Project $project, UpdateProjectDTO $data): Project
    {
        if ($data->status === ProjectStatus::Completed && $this->projects->hasUnfinishedTasks($project)) {
            throw new ProjectHasUnfinishedTasksException;
        }

        $project->fill($data->toArray());
        $this->bitrix->update($actor, $project);
        $this->projects->save($project);

        return $this->projects->freshWithMembers($project);
    }

    /** Deleting here only archives the Bitrix group. */
    public function delete(User $actor, Project $project): void
    {
        $this->bitrix->delete($actor, $project);
        $this->projects->delete($project);
    }

    /** Archives an active project, or reopens an archived one. */
    public function toggleArchive(User $actor, Project $project): Project
    {
        $project->status = $project->status === ProjectStatus::Archived ? ProjectStatus::Active : ProjectStatus::Archived;
        $this->bitrix->update($actor, $project);
        $this->projects->save($project);

        return $this->projects->freshWithMembers($project);
    }

    public function members(Project $project): Collection
    {
        return $this->projects->members($project);
    }

    public function addMember(User $actor, Project $project, AddProjectMemberDTO $member): Project
    {
        $userId = $member->userId;
        $role = $member->role;

        if ($this->projects->hasMember($project, $userId)) {
            throw new ProjectMemberAlreadyAddedException;
        }

        $this->bitrix->addMember($actor, $project, $this->users->findOrFail($userId), $role);
        $this->projects->attachMember($project, $userId, $role);
        $this->log($actor, $project, 'member_added', "User ID {$userId} was added to project '{$project->title}'.");

        return $this->projects->freshWithMembers($project);
    }

    public function removeMember(User $actor, Project $project, User $member): Project
    {
        if (! $this->projects->hasMember($project, $member->id)) {
            throw new ProjectMemberNotFoundException;
        }

        $this->bitrix->removeMember($actor, $project, $member);
        $this->projects->detachMember($project, $member->id);
        $this->log($actor, $project, 'member_removed', "{$member->name} was removed from project '{$project->title}'.");

        return $this->projects->freshWithMembers($project);
    }

    private function log(User $actor, Project $project, string $action, string $description): void
    {
        $this->activities->create([
            'user_id' => $actor->id,
            'project_id' => $project->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
