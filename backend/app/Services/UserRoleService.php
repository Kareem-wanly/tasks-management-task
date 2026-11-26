<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\User\SyncUserRolesDTO;
use App\Enums\SystemRole;
use App\Exceptions\User\AdministratorRoleChangeDeniedException;
use App\Exceptions\User\LastAdministratorException;
use App\Exceptions\User\RoleAssignmentDeniedException;
use App\Models\User;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\UserRoleServiceInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Which roles a user has. Nobody hands out permissions they don't hold,
 * only an Administrator touches the Administrator role, and the last
 * Administrator keeps it.
 */
final readonly class UserRoleService implements UserRoleServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private PermissionRepositoryInterface $permissions,
        private ConnectionInterface $database,
    ) {}

    /** @return Collection<int, \App\Models\Role> */
    public function list(User $user): Collection
    {
        return $this->users->roles($user);
    }

    public function sync(User $actor, User $user, SyncUserRolesDTO $roles): User
    {
        $roleIds = $roles->roleIds;

        $actorIsAdmin = $actor->hasRole(SystemRole::Administrator);

        if (! $actorIsAdmin && $this->permissions->idsGrantedByRoles($roleIds)->diff($actor->permissionIds())->isNotEmpty()) {
            throw new RoleAssignmentDeniedException;
        }

        return $this->database->transaction(function () use ($actorIsAdmin, $user, $roleIds): User {
            $adminRole = $this->roles->findByName(SystemRole::Administrator->value);

            if ($adminRole instanceof \App\Models\Role) {
                $hasAdmin = $this->users->hasRoleId($user, $adminRole->id);
                $keepsAdmin = in_array($adminRole->id, $roleIds);

                if (($keepsAdmin || $hasAdmin) && ! $actorIsAdmin) {
                    throw new AdministratorRoleChangeDeniedException;
                }

                if ($hasAdmin && ! $keepsAdmin && $this->users->countWithRoleForUpdate($adminRole) <= 1) {
                    throw new LastAdministratorException;
                }
            }

            $this->users->syncRoles($user, $roleIds);

            return $this->users->withRoles($user);
        });
    }
}
