<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    /**
     * Newest first, with roles.
     *
     * @param  array{search?: ?string, role?: ?string}  $filters  role: a role name or ID.
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /** Active users (id, name, email) for dropdowns. */
    public function activeOptions(int $limit): Collection;

    public function findByEmail(string $email): ?User;

    /** Throws ModelNotFoundException (404) when there is none. */
    public function findOrFail(int $id): User;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): User;

    public function save(User $user): void;

    /** @param  array<string, mixed>  $attributes */
    public function update(User $user, array $attributes): void;

    public function attachRole(User $user, int $roleId): void;

    /** @param  list<int>  $roleIds */
    public function syncRoles(User $user, array $roleIds): void;

    public function roles(User $user): Collection;

    public function hasRoleId(User $user, int $roleId): bool;

    /** How many users hold the role, locking their rows until the transaction ends. */
    public function countWithRoleForUpdate(Role $role): int;

    /** Ends every API session of the user. */
    public function revokeTokens(User $user): void;

    /** A new API (Sanctum) token; returns its plain text. */
    public function createApiToken(User $user, string $name): string;

    /** Ends the session the current request was made with. */
    public function revokeCurrentToken(User $user): void;

    /** The user with roles loaded. */
    public function withRoles(User $user): User;
}
