<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentPermissionRepository implements PermissionRepositoryInterface
{
    /** @return Collection<int, Permission> */
    public function all(): Collection
    {
        return Permission::all(['id', 'name', 'display_name', 'description']);
    }

    /** @return Collection<int, int<0, max>> */
    public function idsGrantedByRoles(array $roleIds): Collection
    {
        return Permission::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))->get(['permissions.id'])
            ->map(fn (Permission $permission): int => $permission->id);
    }
}
