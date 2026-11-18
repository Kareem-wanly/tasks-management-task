<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Integrations\Bitrix24\BitrixProjectPusher;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Projects and their members. Changes to a project linked to Bitrix24 go to
 * Bitrix first (BitrixProjectPusher) and are saved here only when Bitrix
 * accepts them.
 */
class ProjectService
{
    /** Dropdowns never list more than this many projects. */
    private const OPTIONS_LIMIT = 1000;

    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly UserRepositoryInterface $users,
        private readonly ActivityLogRepositoryInterface $activities,
        private readonly BitrixProjectPusher $bitrix,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function list(User $user, array $filters, int $perPage): LengthAwarePaginator
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
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Project
    {
        $project = new Project([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'owner_id' => $actor->id,
            'status' => $data['status'] ?? 'active',
        ]);

        $this->bitrix->create($actor, $project);

        DB::transaction(function () use ($actor, $project) {
            $this->projects->save($project);
            // The same role the Bitrix import gives the group owner.
            $this->projects->attachMember($project, $actor->id, 'owner');
        });

        return $this->projects->freshWithMembers($project);
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $actor, Project $project, array $data): Project
    {
        if (($data['status'] ?? null) === 'completed' && $this->projects->hasUnfinishedTasks($project)) {
            throw new BusinessRuleException('Cannot complete project with unfinished tasks.', 422, [
                'status' => ['Project has pending or in-progress tasks that must be completed first.'],
            ]);
        }

        $project->fill($data);
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
        $project->status = $project->status === 'archived' ? 'active' : 'archived';
        $this->bitrix->update($actor, $project);
        $this->projects->save($project);

        return $this->projects->freshWithMembers($project);
    }

    public function members(Project $project): Collection
    {
        return $this->projects->members($project);
    }

    public function addMember(User $actor, Project $project, int $userId, string $role): Project
    {
        if ($this->projects->hasMember($project, $userId)) {
            throw new BusinessRuleException('User is already a member of this project.', 422,
                ['user_id' => ['The specified user is already attached to this project.']], ['success' => false]);
        }

        $this->bitrix->addMember($actor, $project, $this->users->findOrFail($userId), $role);
        $this->projects->attachMember($project, $userId, $role);
        $this->log($actor, $project, 'member_added', "User ID {$userId} was added to project '{$project->title}'.");

        return $this->projects->freshWithMembers($project);
    }

    public function removeMember(User $actor, Project $project, User $member): Project
    {
        if (! $this->projects->hasMember($project, $member->id)) {
            throw new BusinessRuleException('User is not a member of this project.', 404,
                ['user_id' => ['The specified user is not attached to this project.']], ['success' => false]);
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
