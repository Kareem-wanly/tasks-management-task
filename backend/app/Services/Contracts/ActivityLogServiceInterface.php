<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityLogServiceInterface
{
    /** @return LengthAwarePaginator<int, \App\Models\ActivityLog> */
    public function forProject(Project $project, int $perPage): LengthAwarePaginator;

    /**
     * Administrators see everything; others see the projects they own or belong to.
     *
     * @return LengthAwarePaginator<int, \App\Models\ActivityLog>
     */
    public function forUser(User $user, int $perPage): LengthAwarePaginator;
}
