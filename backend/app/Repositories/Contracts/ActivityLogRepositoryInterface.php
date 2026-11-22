<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityLogRepositoryInterface
{
    /**
     * Newest first, with who did it.
     *
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function paginateForProject(Project $project, int $perPage): LengthAwarePaginator;

    /**
     * Newest first: every entry, or (onlyTheirProjects) those of projects the user owns or belongs to.
     *
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function paginateForUser(User $user, bool $onlyTheirProjects, int $perPage): LengthAwarePaginator;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): ActivityLog;
}
