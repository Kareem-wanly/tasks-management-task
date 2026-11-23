<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Project;
use App\Services\Contracts\ActivityLogServiceInterface;
use Illuminate\Support\Facades\Auth;

final readonly class ProjectObserver
{
    public function __construct(private ActivityLogServiceInterface $activities) {}

    public function created(Project $project): void
    {
        $this->activities->logProjectCreated($project, Auth::user());
    }

    public function updated(Project $project): void
    {
        $this->activities->logProjectUpdated($project, Auth::user());
    }

    public function deleted(Project $project): void
    {
        $this->activities->logProjectDeleted($project, Auth::user());
    }
}
