<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    
    public function viewAny(User $currentUser): bool
    {
        return $currentUser->hasPermission('users.view');
    }

    public function view(User $currentUser, User $user): bool
    {
        return $currentUser->hasPermission('users.view');
    }

    public function create(User $currentUser): bool
    {
        return $currentUser->hasPermission('users.create');
    }

    public function update(User $currentUser, User $user): bool
    {
        return $currentUser->hasPermission('users.update');
    }

    public function delete(User $currentUser, User $user): bool
    {
        return $currentUser->hasPermission('users.delete');
    }

    public function manageRoles(User $currentUser): bool
    {
        return $currentUser->hasPermission('roles.assign');
    }

    public function viewRoles(User $currentUser, User $user): bool
    {
        return $currentUser->is($user) || $currentUser->hasPermission('users.view');
    }

}
