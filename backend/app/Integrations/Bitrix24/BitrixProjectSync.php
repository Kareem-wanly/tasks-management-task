<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Enums\ProjectStatus;
use App\Integrations\Bitrix24\Mappers\BitrixProjectMapper;
use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates or updates our copy of a Bitrix24 workgroup and its members.
 * Bitrix wins on conflict. Users must be imported first (step 2.1).
 */
class BitrixProjectSync
{
    public function __construct(
        private readonly BitrixProjectMapper $mapper,
        private readonly ProjectRepositoryInterface $projects,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @param  array  $group  A workgroup.list row.
     * @param  array  $members  The sonet_group.user.get result for it.
     * @return Project|null Null when the owner isn't one of our users.
     */
    public function upsert(array $group, array $members): ?Project
    {
        $attributes = $this->mapper->attributes($group);
        $ownerBitrixId = $this->mapper->ownerBitrixId($group);
        $ownerId = $this->users->idByBitrixId($ownerBitrixId);

        if ($ownerId === null) {
            Log::warning('Bitrix24 group skipped: its owner is not imported.', [
                'bitrix_group_id' => $attributes['bitrix_group_id'],
                'owner_bitrix_id' => $ownerBitrixId,
            ]);

            return null;
        }

        return DB::transaction(function () use ($group, $members, $attributes, $ownerId): Project {
            $project = $this->projects->findByBitrixGroupId($attributes['bitrix_group_id'], withTrashed: true) ?? new Project;

            if ($project->trashed()) {
                // Deleting here archives the Bitrix group (ProjectService::delete),
                // so a closed group is our own delete: leave it deleted. Reopened in
                // Bitrix means someone wants it back.
                if ($this->mapper->isClosed($group)) {
                    return $project;
                }
                $this->projects->restore($project);
            }

            $project->forceFill([...$attributes, 'owner_id' => $ownerId]);
            $project->status = $this->status($project, $this->mapper->isClosed($group));

            // Save only real changes, so ProjectObserver doesn't log an
            // "updated" entry on every import.
            if (! $project->exists || $project->isDirty()) {
                $this->projects->save($project);
            }

            // Bookkeeping, not a change: updated_at keeps the last real change.
            $this->projects->markSynced($project);

            $this->syncMembers($project, $this->mapper->members($members));

            return $project;
        });
    }

    /**
     * Bitrix only knows open/closed, so it decides archived vs not; our
     * other statuses (in_progress, on_hold, ...) stay local.
     */
    private function status(Project $project, bool $closed): ProjectStatus
    {
        if ($closed) {
            return ProjectStatus::Archived;
        }

        return ! $project->exists || $project->status === ProjectStatus::Archived ? ProjectStatus::Active : $project->status;
    }

    /**
     * @param  array<int, string>  $roles  Bitrix user ID => role
     */
    private function syncMembers(Project $project, array $roles): void
    {
        $userIds = $this->users->idsByBitrixIds(array_keys($roles));

        $missing = array_diff(array_keys($roles), array_keys($userIds));
        if ($missing !== []) {
            Log::warning('Bitrix24 group members skipped: not imported.', [
                'bitrix_group_id' => $project->bitrix_group_id,
                'bitrix_user_ids' => array_values($missing),
            ]);
        }

        $pivot = [];
        foreach ($userIds as $bitrixId => $userId) {
            $pivot[$userId] = ['role' => $roles[$bitrixId]];
        }

        // Members removed in Bitrix are removed here too.
        $this->projects->syncMembersWithRoles($project, $pivot);
    }
}
