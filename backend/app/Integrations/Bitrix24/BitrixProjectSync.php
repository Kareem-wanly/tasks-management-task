<?php

namespace App\Integrations\Bitrix24;

use App\Integrations\Bitrix24\Mappers\BitrixProjectMapper;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates or updates our copy of a Bitrix24 workgroup and its members.
 * Bitrix wins on conflict. Users must be imported first (step 2.1).
 */
class BitrixProjectSync
{
    public function __construct(private readonly BitrixProjectMapper $mapper) {}

    /**
     * @param  array  $group  A workgroup.list row.
     * @param  array  $members  The sonet_group.user.get result for it.
     * @return Project|null Null when the owner isn't one of our users.
     */
    public function upsert(array $group, array $members): ?Project
    {
        $attributes = $this->mapper->attributes($group);
        $ownerBitrixId = $this->mapper->ownerBitrixId($group);
        $ownerId = User::where('bitrix_user_id', $ownerBitrixId)->value('id');

        if ($ownerId === null) {
            Log::warning('Bitrix24 group skipped: its owner is not imported.', [
                'bitrix_group_id' => $attributes['bitrix_group_id'],
                'owner_bitrix_id' => $ownerBitrixId,
            ]);

            return null;
        }

        return DB::transaction(function () use ($group, $members, $attributes, $ownerId) {
            $project = Project::withTrashed()->where('bitrix_group_id', $attributes['bitrix_group_id'])->first()
                ?? new Project;

            if ($project->trashed()) {
                $project->restore();
            }

            $project->forceFill([...$attributes, 'owner_id' => $ownerId]);
            $project->status = $this->status($project, $this->mapper->isClosed($group));

            // Save only real changes, so ProjectObserver doesn't log an
            // "updated" entry on every import.
            if (! $project->exists || $project->isDirty()) {
                $project->save();
            }

            $project->forceFill(['bitrix_synced_at' => now()])->saveQuietly();

            $this->syncMembers($project, $this->mapper->members($members));

            return $project;
        });
    }

    /**
     * Bitrix only knows open/closed, so it decides archived vs not; our
     * other statuses (in_progress, on_hold, ...) stay local.
     */
    private function status(Project $project, bool $closed): string
    {
        if ($closed) {
            return 'archived';
        }

        return ! $project->exists || $project->status === 'archived' ? 'active' : $project->status;
    }

    /**
     * @param  array<int, string>  $roles  Bitrix user ID => role
     */
    private function syncMembers(Project $project, array $roles): void
    {
        $userIds = User::whereIn('bitrix_user_id', array_keys($roles))->pluck('id', 'bitrix_user_id');

        $missing = array_diff(array_keys($roles), $userIds->keys()->all());
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
        $project->members()->sync($pivot);
    }
}
