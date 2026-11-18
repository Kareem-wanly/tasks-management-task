<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('permissions.view');
    }
}
