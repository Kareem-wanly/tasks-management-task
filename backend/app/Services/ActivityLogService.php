<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The activity log, written by the observers and services.
 */
class ActivityLogService
{
    public function __construct(private readonly ActivityLogRepositoryInterface $activities) {}

    public function forProject(Project $project, int $perPage): LengthAwarePaginator
    {
        return $this->activities->paginateForProject($project, $perPage);
    }

    /** Administrators see everything; others see the projects they own or belong to. */
    public function forUser(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->activities->paginateForUser($user, ! $user->hasRole('Administrator'), $perPage);
    }
}
