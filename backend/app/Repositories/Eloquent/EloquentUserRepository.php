<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\User\UserFiltersDTO;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Concerns\ReadsColumns;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentUserRepository implements UserRepositoryInterface
{
    use ReadsColumns;

    /** @return LengthAwarePaginator<int, User> */
    public function paginate(UserFiltersDTO $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::with('roles:id,name,display_name');

        if ($filters->search !== null) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters->search);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        if ($filters->role !== null) {
            $role = $filters->role;
            $query->whereHas('roles', fn ($q) => $q->where('name', $role)->orWhere('id', $role));
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    /** @return Collection<int, User> */
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

    /** @return Collection<int, Role> */
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

    public function findByBitrixId(int $bitrixId): ?User
    {
        return User::where('bitrix_user_id', $bitrixId)->first();
    }

    public function idByBitrixId(int $bitrixId): ?int
    {
        return $bitrixId === 0 ? null : self::toIntOrNull(User::where('bitrix_user_id', $bitrixId)->value('id'));
    }

    public function idsByBitrixIds(array $bitrixIds): array
    {
        $ids = [];
        foreach (User::whereIn('bitrix_user_id', $bitrixIds)->get(['id', 'bitrix_user_id']) as $user) {
            $ids[self::toInt($user->bitrix_user_id)] = $user->id;
        }

        return $ids;
    }

    public function bitrixIdOf(int $userId): ?int
    {
        $bitrixId = User::whereKey($userId)->value('bitrix_user_id');

        return self::toIntOrNull($bitrixId);
    }

    /** @return Collection<int, User> */
    public function findMany(array $userIds): Collection
    {
        return User::whereIn('id', $userIds)->get(['id', 'name', 'bitrix_user_id']);
    }

    public function allBitrixIds(): array
    {
        return self::toIntList(User::whereNotNull('bitrix_user_id')->pluck('bitrix_user_id'));
    }

    public function findUnlinkedByEmail(string $email): ?User
    {
        return User::whereRaw('LOWER(email) = ?', [strtolower($email)])->whereNull('bitrix_user_id')->first();
    }

    public function emailTaken(string $email, ?int $exceptUserId = null): bool
    {
        return User::whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->exists();
    }

    public function forceCreate(array $attributes): User
    {
        return User::forceCreate($attributes);
    }
}
