<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    
    public function before(User $currentUser, string $ability): ?bool
    {
        if ($currentUser->hasRole('Administrator')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $currentUser): bool
    {
        return $currentUser->hasPermission('users.view') || $currentUser->hasPermission('users.manage');
    }

    public function view(User $currentUser, User $user): bool
    {
        return $currentUser->hasPermission('users.view') || $currentUser->hasPermission('users.manage');
    }

    public function create(User $currentUser): bool
    {
        return $currentUser->hasPermission('users.create') || $currentUser->hasPermission('users.manage');
    }

    public function update(User $currentUser, User $user): bool
    {
        return $currentUser->hasPermission('users.update') || $currentUser->hasPermission('users.manage');
    }

    public function delete(User $currentUser, User $user): bool
    {
        return $currentUser->hasPermission('users.delete') || $currentUser->hasPermission('users.manage');
    }

    public function manageRoles(User $currentUser): bool
    {
        return $currentUser->hasPermission('roles.assign') || $currentUser->hasPermission('users.manage');
    }
}