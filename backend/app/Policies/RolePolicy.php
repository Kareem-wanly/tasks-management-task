<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view') || $user->hasPermission('roles.manage');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.view') || $user->hasPermission('roles.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create') || $user->hasPermission('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.update') || $user->hasPermission('roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.delete') || $user->hasPermission('roles.manage');
    }
}