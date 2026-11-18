<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;

class RoleRepository implements RoleRepositoryInterface
{
    public function allWithPermissions(): Collection
    {
        return Role::with('permissions:id,name,description')
            ->withCount(['permissions', 'users'])
            ->get();
    }

    public function create(array $attributes): Role
    {
        return Role::create($attributes);
    }

    public function update(Role $role, array $attributes): void
    {
        $role->update($attributes);
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function syncPermissions(Role $role, array $permissionIds): void
    {
        $role->permissions()->sync($permissionIds);
    }

    public function withPermissions(Role $role): Role
    {
        return $role->load('permissions:id,name,description');
    }

    public function findByName(string $name): ?Role
    {
        return Role::where('name', $name)->first();
    }

    public function memberRole(): Role
    {
        return Role::firstOrCreate(['name' => 'Member'], [
            'display_name' => 'Member',
            'description' => 'Standard team member with basic access',
        ]);
    }
}
