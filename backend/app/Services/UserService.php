<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\User\InviteUserDTO;
use App\DTOs\User\SetUserActiveDTO;
use App\DTOs\User\UpdateUserDTO;
use App\DTOs\User\UserFiltersDTO;
use App\Exceptions\User\AdministratorChangeDeniedException;
use App\Exceptions\User\CannotDeactivateOwnAccountException;
use App\Integrations\Bitrix24\BitrixUserPusher;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\UserServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Employees. Changes go to Bitrix24 first (BitrixUserPusher) and are saved
 * here only when Bitrix accepts them.
 */
final readonly class UserService implements UserServiceInterface
{
    /** Dropdowns never list more than this many people. */
    private const OPTIONS_LIMIT = 1000;

    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private BitrixUserPusher $bitrix,
    ) {}

    public function list(UserFiltersDTO $filters, int $perPage): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $perPage);
    }

    /** Only active employees can be picked as assignees or members. */
    public function options(): Collection
    {
        return $this->users->activeOptions(self::OPTIONS_LIMIT);
    }

    public function show(User $user): User
    {
        return $this->users->withRoles($user);
    }

    /**
     * Invites the person to Bitrix24 (Bitrix emails them), then adds them
     * here with the chosen role, or Member.
     */
    public function invite(User $actor, InviteUserDTO $data): User
    {
        $user = new User(['name' => $data->name, 'email' => $data->email, 'password' => $data->password]);
        $this->bitrix->invite($actor, $user);

        DB::transaction(function () use ($user, $data): void {
            $this->users->save($user);
            $this->users->attachRole($user, $data->roleId ?? $this->roles->memberRole()->id);
        });

        return $this->users->withRoles($user);
    }

    public function update(User $actor, User $user, UpdateUserDTO $data): User
    {
        $user->fill($data->toArray());
        $this->bitrix->update($actor, $user);
        $this->users->save($user);

        return $user;
    }

    /**
     * Replaces deleting: Bitrix24 has no user delete over REST, so employees
     * are deactivated (and can be activated again). A deactivated employee
     * can't sign in, and their open sessions end.
     */
    public function setActive(User $actor, User $user, SetUserActiveDTO $data): User
    {
        $active = $data->active;

        if ($user->is($actor)) {
            throw new CannotDeactivateOwnAccountException;
        }
        if ($user->hasRole('Administrator') && ! $actor->hasRole('Administrator')) {
            throw new AdministratorChangeDeniedException;
        }

        $this->bitrix->setActive($actor, $user, $active);
        $user->is_active = $active;
        $this->users->save($user);

        if (! $active) {
            $this->users->revokeTokens($user);
        }

        return $this->users->withRoles($user);
    }
}
