<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Role\CreateRoleDTO;
use App\DTOs\Role\SyncRolePermissionsDTO;
use App\DTOs\Role\UpdateRoleDTO;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

interface RoleServiceInterface
{
    /** @return Collection<int, Role> */
    public function list(): Collection;

    public function show(Role $role): Role;

    public function create(User $actor, CreateRoleDTO $data): Role;

    public function update(User $actor, Role $role, UpdateRoleDTO $data): Role;

    public function syncPermissions(User $actor, Role $role, SyncRolePermissionsDTO $permissions): Role;

    public function delete(Role $role): void;
}
