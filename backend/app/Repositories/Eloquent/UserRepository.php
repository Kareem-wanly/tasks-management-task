<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::with('roles:id,name,display_name');

        if (! empty($filters['search'])) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters['search']);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        if (! empty($filters['role'])) {
            $role = $filters['role'];
            $query->whereHas('roles', fn ($q) => $q->where('name', $role)->orWhere('id', $role));
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function activeOptions(int $limit): Collection
    {
        return User::where('is_active', true)->orderBy('name')->limit($limit)->get(['id', 'name', 'email']);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function save(User $user): void
    {
        $user->save();
    }

    public function update(User $user, array $attributes): void
    {
        $user->update($attributes);
    }

    public function attachRole(User $user, int $roleId): void
    {
        $user->roles()->attach($roleId);
    }

    public function syncRoles(User $user, array $roleIds): void
    {
        $user->roles()->sync($roleIds);
        $user->clearPermissionsCache();
    }

    public function roles(User $user): Collection
    {
        return $user->roles()->get(['roles.id', 'name', 'display_name', 'description']);
    }

    public function hasRoleId(User $user, int $roleId): bool
    {
        return $user->roles()->where('roles.id', $roleId)->exists();
    }

    public function countWithRoleForUpdate(Role $role): int
    {
        return User::whereHas('roles', fn ($q) => $q->where('roles.id', $role->id))->lockForUpdate()->count();
    }

    public function revokeTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    public function createApiToken(User $user, string $name): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function withRoles(User $user): User
    {
        return $user->load('roles:id,name,display_name');
    }
}
