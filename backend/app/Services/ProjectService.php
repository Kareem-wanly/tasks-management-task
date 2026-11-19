<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Project\AddProjectMemberDTO;
use App\DTOs\Project\CreateProjectDTO;
use App\DTOs\Project\ProjectFiltersDTO;
use App\DTOs\Project\UpdateProjectDTO;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\Project\ProjectHasUnfinishedTasksException;
use App\Exceptions\Project\ProjectMemberAlreadyAddedException;
use App\Exceptions\Project\ProjectMemberNotFoundException;
use App\Integrations\Bitrix24\BitrixFingerprint;
use App\Integrations\Bitrix24\Contracts\BitrixProjectRepositoryInterface;
use App\Models\BitrixOutboundOp;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\BitrixOutboundOpRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\ProjectServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Projects and their members. Changes to a project linked to Bitrix24 go to
 * Bitrix first and are saved here only when Bitrix accepts them (a refusal
 * throws, and nothing is saved).
 *
 * Group changes go with the employee's own token, so they need a Bitrix
 * sign-in. Member changes go with the service account (Bitrix lets only
 * administrators add members or change their role); our policy has already
 * allowed them.
 */
final readonly class ProjectService implements ProjectServiceInterface
{
    /** Dropdowns never list more than this many projects. */
    private const OPTIONS_LIMIT = 1000;

    public function __construct(
        private ProjectRepositoryInterface $projects,
        private UserRepositoryInterface $users,
        private ActivityLogRepositoryInterface $activities,
        private BitrixProjectRepositoryInterface $bitrixProjects,
        private BitrixOutboundOpRepositoryInterface $ops,
        private BitrixFingerprint $fingerprints,
        private ConnectionInterface $database,
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

        $this->createInBitrix($actor, $project);

        $this->database->transaction(function () use ($actor, $project): void {
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
        $this->updateInBitrix($actor, $project);
        $this->projects->save($project);

        return $this->projects->freshWithMembers($project);
    }

    /** Deleting here only archives the Bitrix group. */
    public function delete(User $actor, Project $project): void
    {
        $this->deleteInBitrix($actor, $project);
        $this->projects->delete($project);
    }

    /** Archives an active project, or reopens an archived one. */
    public function toggleArchive(User $actor, Project $project): Project
    {
        $project->status = $project->status === ProjectStatus::Archived ? ProjectStatus::Active : ProjectStatus::Archived;
        $this->updateInBitrix($actor, $project);
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

        $this->addMemberInBitrix($actor, $project, $this->users->findOrFail($userId), $role);
        $this->projects->attachMember($project, $userId, $role);
        $this->log($actor, $project, 'member_added', "User ID {$userId} was added to project '{$project->title}'.");

        return $this->projects->freshWithMembers($project);
    }

    public function removeMember(User $actor, Project $project, User $member): Project
    {
        if (! $this->projects->hasMember($project, $member->id)) {
            throw new ProjectMemberNotFoundException;
        }

        $this->removeMemberInBitrix($actor, $project, $member);
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

    /** New projects are Bitrix projects; the creator becomes the group owner. */
    private function createInBitrix(User $actor, Project $project): void
    {
        $this->ensureSignedInWithBitrix($actor);
        $project->bitrix_group_id = $this->bitrixProjects->createGroup($actor, $project);
        $project->bitrix_synced_at = now();
        $this->recordOutbound($actor, $project, 'create');
    }

    /** Sends the unsaved changes. Of `status`, Bitrix keeps only the archived state (CLOSED). */
    private function updateInBitrix(User $actor, Project $project): void
    {
        if (! $project->bitrix_group_id) {
            return;
        }

        $columns = $this->bitrixProjects->syncedColumns(array_keys($project->getDirty()));
        $isArchived = $project->status === ProjectStatus::Archived;
        $archiving = $project->isDirty('status') && ($project->getOriginal('status') === ProjectStatus::Archived) !== $isArchived;
        if ($columns === [] && ! $archiving) {
            return;
        }

        $this->ensureSignedInWithBitrix($actor);
        $this->bitrixProjects->updateGroup($actor, $project, $columns, $archiving ? $isArchived : null);
        $project->bitrix_synced_at = now();
        $this->recordOutbound($actor, $project, 'update');
    }

    /** Deleting here only archives the Bitrix group: deleting one there is permanent and takes its feed and files. */
    private function deleteInBitrix(User $actor, Project $project): void
    {
        if (! $project->bitrix_group_id) {
            return;
        }

        $this->ensureSignedInWithBitrix($actor);
        $this->bitrixProjects->closeGroup($actor, $project->bitrix_group_id);
        $this->ops->record(BitrixOutboundOp::PROJECT, $project->bitrix_group_id, 'delete', null, $actor);
    }

    private function addMemberInBitrix(User $actor, Project $project, User $member, ProjectMemberRole $role): void
    {
        if (! $project->bitrix_group_id) {
            return;
        }

        $this->ensureSignedInWithBitrix($actor);
        $this->bitrixProjects->addMember($project->bitrix_group_id, $this->bitrixUserId($member), $role);
    }

    private function removeMemberInBitrix(User $actor, Project $project, User $member): void
    {
        if (! $project->bitrix_group_id) {
            return;
        }

        $this->ensureSignedInWithBitrix($actor);
        $this->bitrixProjects->removeMember($project->bitrix_group_id, $this->bitrixUserId($member));
    }

    /** Changes to a Bitrix project are made with the employee's own Bitrix token. */
    private function ensureSignedInWithBitrix(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }

    /** Only Bitrix24 users can be members of a Bitrix project. */
    private function bitrixUserId(User $user): int
    {
        return $user->bitrix_user_id
            ?? throw BitrixException::cannotSync("{$user->name} is not a Bitrix24 user, so they cannot be added to this project.");
    }

    /** Remembered so the echo of our change is recognised, not taken for a change made in Bitrix. */
    private function recordOutbound(User $actor, Project $project, string $action): void
    {
        $this->ops->record(BitrixOutboundOp::PROJECT, (int) $project->bitrix_group_id, $action, $this->fingerprints->project($project), $actor);
    }
}
