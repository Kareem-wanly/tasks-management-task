<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\User\SyncUserRolesDTO;
use App\Models\User;
use Illuminate\Support\Collection;

interface UserRoleServiceInterface
{
    public function list(User $user): Collection;

    public function sync(User $actor, User $user, SyncUserRolesDTO $roles): User;
}
