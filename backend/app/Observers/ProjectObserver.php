<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;

class ProjectObserver
{
    public function created(Project $project): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id() ?? $project->owner_id,
            'project_id'  => $project->id,
            'action'      => 'created',
            'description' => "Project '{$project->title}' was created.",
            'metadata'    => ['title' => $project->title],
        ]);
    }

    public function updated(Project $project): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id() ?? $project->owner_id,
            'project_id'  => $project->id,
            'action'      => 'updated',
            'description' => "Project '{$project->title}' was updated.",
            'metadata'    => $project->getChanges(),
        ]);
    }

    public function deleted(Project $project): void
    {
        ActivityLog::create([
            'user_id'     => Auth::id() ?? $project->owner_id,
            'project_id'  => $project->id,
            'action'      => 'deleted',
            'description' => "Project '{$project->title}' was deleted.",
        ]);
    }
}