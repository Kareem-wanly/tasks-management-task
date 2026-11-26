<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Project\AddProjectMemberDTO;
use App\DTOs\Project\CreateProjectDTO;
use App\DTOs\Project\ProjectFiltersDTO;
use App\DTOs\Project\UpdateProjectDTO;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectServiceInterface
{
    /** @return LengthAwarePaginator<int, Project> */
    public function list(User $user, ProjectFiltersDTO $filters, int $perPage): LengthAwarePaginator;

    public function show(Project $project): Project;

    /**
     * Forms offer active projects only; list filters ask for archived ones too.
     *
     * @return Collection<int, Project>
     */
    public function options(User $user, bool $includeArchived): Collection;

    /**
     * A new project is a Bitrix project; its creator owns it.
     */
    public function create(User $actor, CreateProjectDTO $data): Project;

    public function update(User $actor, Project $project, UpdateProjectDTO $data): Project;

    /** Deleting here only archives the Bitrix group. */
    public function delete(User $actor, Project $project): void;

    /** Archives an active project, or reopens an archived one. */
    public function toggleArchive(User $actor, Project $project): Project;

    /** @return Collection<int, User> */
    public function members(Project $project): Collection;

    public function addMember(User $actor, Project $project, AddProjectMemberDTO $member): Project;

    public function removeMember(User $actor, Project $project, User $member): Project;

    // --- For the Bitrix24 import and sync: Bitrix is the source, so nothing here goes back to Bitrix.

    public function findByBitrixGroupId(int $bitrixGroupId): ?Project;

    public function idByBitrixGroupId(int $bitrixGroupId): ?int;

    /**
     * The Bitrix IDs of all our linked groups.
     *
     * @return list<int>
     */
    public function allBitrixGroupIds(): array;

    /**
     * Step 5.3: the Bitrix IDs of projects deleted here. Deleting archives the
     * group, so Bitrix still lists it (closed): that is not a difference.
     *
     * @return list<int>
     */
    public function trashedBitrixGroupIds(): array;

    /** Deletes our copy of a group deleted in Bitrix (with its tasks), here only. */
    public function removeGoneFromBitrix(Project $project): void;

    /**
     * Deletes our copies of groups deleted in Bitrix, here only.
     *
     * @param  list<int>  $bitrixGroupIds
     * @return int How many were deleted.
     */
    public function removeAllGoneFromBitrix(array $bitrixGroupIds): int;
}
