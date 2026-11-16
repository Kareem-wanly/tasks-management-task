<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Roles and the permissions they grant. Only an Administrator touches the
 * Administrator role, and nobody grants permissions they don't have.
 */
class RoleService
{
    /** Seeded roles: never deleted, never renamed, their names never reused. */
    public const SYSTEM_ROLES = ['Administrator', 'Project Manager', 'Member'];

    public function __construct(private readonly RoleRepositoryInterface $roles) {}

    public function list(): Collection
    {
        return $this->roles->allWithPermissions();
    }

    public function show(Role $role): Role
    {
        return $this->roles->withPermissions($role);
    }

    /** @param  array<string, mixed>  $data */
    public function create(User $actor, array $data): Role
    {
        $role = $this->roles->create([
            'name' => $data['name'],
            'display_name' => $data['display_name'] ?? $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if (! empty($data['permissions'])) {
            $this->guardPermissionGrant($actor, $role, $data['permissions']);
            $this->roles->syncPermissions($role, $data['permissions']);
        }

        return $this->roles->withPermissions($role);
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $actor, Role $role, array $data): Role
    {
        $newName = $data['name'] ?? null;
        $isSystem = in_array($role->name, self::SYSTEM_ROLES, true);

        // System roles keep their names, so the app can rely on them.
        if ($isSystem && $newName !== null && $newName !== $role->name) {
            throw BusinessRuleException::forbidden('System roles cannot be renamed.');
        }
        if (! $isSystem && $newName !== null && in_array($newName, self::SYSTEM_ROLES, true)) {
            throw BusinessRuleException::forbidden('Cannot rename a role to a reserved system role name.');
        }

        $this->roles->update($role, array_intersect_key($data, array_flip(['name', 'display_name', 'description'])));

        if (array_key_exists('permissions', $data)) {
            $this->guardPermissionGrant($actor, $role, $data['permissions']);
            $this->roles->syncPermissions($role, $data['permissions']);
        }

        return $this->roles->withPermissions($role);
    }

    /** @param  list<int>  $permissionIds */
    public function syncPermissions(User $actor, Role $role, array $permissionIds): Role
    {
        $this->guardPermissionGrant($actor, $role, $permissionIds);
        $this->roles->syncPermissions($role, $permissionIds);

        return $this->roles->withPermissions($role);
    }

    public function delete(Role $role): void
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            throw BusinessRuleException::forbidden("Protected system role [{$role->name}] cannot be deleted");
        }

        $this->roles->delete($role);
    }

    /** @param  list<int>  $permissionIds */
    private function guardPermissionGrant(User $actor, Role $role, array $permissionIds): void
    {
        if ($actor->hasRole('Administrator')) {
            return;
        }

        if ($role->name === 'Administrator') {
            throw BusinessRuleException::forbidden('Only an Administrator can modify permissions for this role.');
        }

        if (array_diff($permissionIds, $actor->permissionIds()->all()) !== []) {
            throw BusinessRuleException::forbidden('You cannot grant permissions that you do not possess.');
        }
    }
}
