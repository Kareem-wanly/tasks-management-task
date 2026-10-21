<?php

namespace App\Policies;

use App\Models\User;

class PermissionPolicy
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
        return $user->hasPermission('permissions.view');
    }
}