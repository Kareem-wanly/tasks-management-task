<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Role\CreateRoleDTO;
use App\DTOs\Role\SyncRolePermissionsDTO;
use App\DTOs\Role\UpdateRoleDTO;
use App\Enums\SystemRole;
use App\Exceptions\Role\PermissionGrantDeniedException;
use App\Exceptions\Role\ReservedRoleNameException;
use App\Exceptions\Role\RolePermissionsChangeDeniedException;
use App\Exceptions\Role\SystemRoleCannotBeDeletedException;
use App\Exceptions\Role\SystemRoleCannotBeRenamedException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\Contracts\RoleServiceInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Roles and the permissions they grant. Only an Administrator touches the
 * Administrator role, and nobody grants permissions they don't have.
 */
final readonly class RoleService implements RoleServiceInterface
{
    /** Seeded roles: never deleted, never renamed, their names never reused. */
    public function __construct(
        private RoleRepositoryInterface $roles,
        private ConnectionInterface $database,
    ) {}

    public function list(): Collection
    {
        return $this->roles->allWithPermissions();
    }

    public function show(Role $role): Role
    {
        return $this->roles->withPermissions($role);
    }

    public function create(User $actor, CreateRoleDTO $data): Role
    {
        // A refused permission must not leave the new role behind.
        $role = $this->database->transaction(function () use ($actor, $data): Role {
            $role = $this->roles->create([
                'name' => $data->name,
                'display_name' => $data->displayName ?? $data->name,
                'description' => $data->description,
            ]);

            if ($data->permissionIds !== []) {
                $this->guardPermissionGrant($actor, $role, $data->permissionIds);
                $this->roles->syncPermissions($role, $data->permissionIds);
            }

            return $role;
        });

        return $this->roles->withPermissions($role);
    }

    public function update(User $actor, Role $role, UpdateRoleDTO $data): Role
    {
        $newName = $data->name;
        $isSystem = in_array($role->name, SystemRole::names(), true);

        // System roles keep their names, so the app can rely on them.
        if ($isSystem && $newName !== null && $newName !== $role->name) {
            throw new SystemRoleCannotBeRenamedException;
        }
        if (! $isSystem && $newName !== null && in_array($newName, SystemRole::names(), true)) {
            throw new ReservedRoleNameException;
        }

        $this->database->transaction(function () use ($actor, $role, $data): void {
            $this->roles->update($role, $data->toArray());

            if ($data->has('permissions')) {
                $this->guardPermissionGrant($actor, $role, $data->permissionIds);
                $this->roles->syncPermissions($role, $data->permissionIds);
            }
        });

        return $this->roles->withPermissions($role);
    }

    public function syncPermissions(User $actor, Role $role, SyncRolePermissionsDTO $permissions): Role
    {
        $this->guardPermissionGrant($actor, $role, $permissions->permissionIds);
        $this->roles->syncPermissions($role, $permissions->permissionIds);

        return $this->roles->withPermissions($role);
    }

    public function delete(Role $role): void
    {
        if (in_array($role->name, SystemRole::names(), true)) {
            throw SystemRoleCannotBeDeletedException::forRole($role->name);
        }

        $this->roles->delete($role);
    }

    /** @param  list<int>  $permissionIds */
    private function guardPermissionGrant(User $actor, Role $role, array $permissionIds): void
    {
        if ($actor->hasRole(SystemRole::Administrator)) {
            return;
        }

        if ($role->name === SystemRole::Administrator->value) {
            throw new RolePermissionsChangeDeniedException;
        }

        if (array_diff($permissionIds, $actor->permissionIds()->all()) !== []) {
            throw new PermissionGrantDeniedException;
        }
    }
}
