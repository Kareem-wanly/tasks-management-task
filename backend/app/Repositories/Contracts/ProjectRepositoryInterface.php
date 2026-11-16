<?php

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
}
