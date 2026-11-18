<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function paginateForProject(Project $project, int $perPage): LengthAwarePaginator
    {
        return ActivityLog::where('project_id', $project->id)->with('user:id,name,email')->latest()->paginate($perPage);
    }

    public function paginateForUser(User $user, bool $onlyTheirProjects, int $perPage): LengthAwarePaginator
    {
        return ActivityLog::with('user:id,name,email')
            ->latest()
            ->when($onlyTheirProjects, fn ($q) => $q->whereHas('project', fn ($p) => $p
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id))))
            ->paginate($perPage);
    }

    public function create(array $attributes): ActivityLog
    {
        return ActivityLog::create($attributes);
    }
}
