<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Collection;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function all(): Collection
    {
        return Permission::all(['id', 'name', 'display_name', 'description']);
    }

    public function idsGrantedByRoles(array $roleIds): Collection
    {
        return Permission::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))->pluck('id');
    }
}
