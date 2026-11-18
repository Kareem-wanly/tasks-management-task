<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectRepositoryInterface
{
    /**
     * Projects the user may open, with their owner and task counts.
     *
     * @param  array{search?: ?string, status?: ?string, member_id?: ?int, sort?: ?string, direction?: ?string}  $filters
     */
    public function paginateAccessible(User $user, array $filters, int $perPage): LengthAwarePaginator;

    /** Owner, members and task counts, for the project page. */
    public function loadForDetail(Project $project): Project;

    /** A fresh copy with owner and members, as write responses return it. */
    public function freshWithMembers(Project $project): Project;

    /** id, title, status of the projects the user may open, for dropdowns. */
    public function optionsFor(User $user, bool $includeArchived, int $limit): Collection;

    public function save(Project $project): void;

    public function delete(Project $project): void;

    public function hasUnfinishedTasks(Project $project): bool;

    /** Members (users.id, name, email). */
    public function members(Project $project): Collection;

    public function hasMember(Project $project, int $userId): bool;

    public function attachMember(Project $project, int $userId, string $role): void;

    public function detachMember(Project $project, int $userId): void;

    public function findByBitrixGroupId(int $bitrixGroupId, bool $withTrashed = false): ?Project;

    public function idByBitrixGroupId(int $bitrixGroupId): ?int;

    /** The Bitrix group of a project, deleted ones too. */
    public function bitrixGroupIdOf(int $projectId): ?int;

    public function restore(Project $project): void;

    /** Notes the sync time without touching updated_at or the activity log. */
    public function markSynced(Project $project): void;

    /** @param  array<int, array{role: string}>  $members  Our user ID => pivot. */
    public function syncMembersWithRoles(Project $project, array $members): void;

    /**
     * The Bitrix IDs of all our linked groups.
     *
     * @return list<int>
     */
    public function allBitrixIds(): array;

    /**
     * Deletes our copies of these Bitrix groups.
     *
     * @param  list<int>  $bitrixGroupIds
     * @return int How many were deleted.
     */
    public function deleteByBitrixIds(array $bitrixGroupIds): int;
}
