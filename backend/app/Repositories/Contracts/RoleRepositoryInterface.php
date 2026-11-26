<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    /**
     * Every role with its permissions and its permission/user counts.
     *
     * @return Collection<int, Role>
     */
    public function allWithPermissions(): Collection;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Role;

    /** @param  array<string, mixed>  $attributes */
    public function update(Role $role, array $attributes): void;

    public function delete(Role $role): void;

    /** @param  list<int>  $permissionIds */
    public function syncPermissions(Role $role, array $permissionIds): void;

    /** The role with its permissions loaded. */
    public function withPermissions(Role $role): Role;

    public function findByName(string $name): ?Role;

    /** The default "Member" role, created when missing. */
    public function memberRole(): Role;
}
