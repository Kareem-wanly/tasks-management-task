<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return null;
    }

    private function isMember(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id 
            || $project->members()->where('users.id', $user->id)->exists();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.view') && $this->isMember($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return ($user->hasPermission('projects.update') || $user->hasPermission('projects.edit')) 
            && $this->isMember($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.delete') && $this->isMember($user, $project);
    }

    public function archive(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.archive') && $this->isMember($user, $project);
    }
}