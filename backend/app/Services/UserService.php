<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\User\InviteUserDTO;
use App\DTOs\User\SetUserActiveDTO;
use App\DTOs\User\UpdateUserDTO;
use App\DTOs\User\UserFiltersDTO;
use App\Enums\SystemRole;
use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\User\AdministratorChangeDeniedException;
use App\Exceptions\User\CannotDeactivateOwnAccountException;
use App\Integrations\Bitrix24\Contracts\BitrixUserRepositoryInterface;
use App\Models\User;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\RoleServiceInterface;
use App\Services\Contracts\UserServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Employees. Changes go to Bitrix24 first, with the acting employee's token,
 * and are saved here only when Bitrix accepts them. Bitrix has no user delete
 * over REST, so employees are deactivated instead.
 */
final readonly class UserService implements UserServiceInterface
{
    /** Dropdowns never list more than this many people. */
    private const OPTIONS_LIMIT = 1000;

    public function __construct(
        private UserRepositoryInterface $users,
        private RoleServiceInterface $roles,
        private BitrixUserRepositoryInterface $bitrixUsers,
        private BitrixPortalRepositoryInterface $portals,
        private ConnectionInterface $database,
    ) {}

    /** @return LengthAwarePaginator<int, User> */
    public function list(UserFiltersDTO $filters, int $perPage): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $perPage);
    }

    /**
     * Only active employees can be picked as assignees or members.
     *
     * @return Collection<int, User>
     */
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
        $this->inviteInBitrix($actor, $user);

        $this->database->transaction(function () use ($user, $data): void {
            $this->users->save($user);
            $this->users->attachRole($user, $data->roleId ?? $this->roles->memberRole()->id);
        });

        return $this->users->withRoles($user);
    }

    public function update(User $actor, User $user, UpdateUserDTO $data): User
    {
        $user->fill($data->toArray());
        $this->updateInBitrix($actor, $user);
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
        if ($user->hasRole(SystemRole::Administrator) && ! $actor->hasRole(SystemRole::Administrator)) {
            throw new AdministratorChangeDeniedException;
        }

        $this->setActiveInBitrix($actor, $user, $active);
        $user->is_active = $active;
        $this->users->save($user);

        if (! $active) {
            $this->users->revokeTokens($user);
        }

        return $this->users->withRoles($user);
    }

    /**
     * Invites the (unsaved) user: Bitrix emails them an invitation, and the
     * returned ID links the accounts, so their later Bitrix sign-in finds this user.
     */
    public function issueApiToken(User $user, string $name): string
    {
        return $this->users->createApiToken($user, $name);
    }

    private function inviteInBitrix(User $actor, User $user): void
    {
        $this->ensureSignedInWithBitrix($actor);
        $department = $this->rootDepartmentId();

        $user->bitrix_user_id = $this->bitrixUsers->invite($actor, $user->email, $user->name, $department);
        $user->bitrix_department_ids = [$department];
        $user->bitrix_synced_at = now();
    }

    /** Sends the user's unsaved name/email changes. */
    private function updateInBitrix(User $actor, User $user): void
    {
        if (! $user->bitrix_user_id || ! $user->isDirty(['name', 'email'])) {
            return;
        }

        $this->ensureSignedInWithBitrix($actor);
        $this->bitrixUsers->update(
            $actor,
            $user->bitrix_user_id,
            $user->isDirty('name') ? $user->name : null,
            $user->isDirty('email') ? $user->email : null,
        );
        $user->bitrix_synced_at = now();
    }

    private function setActiveInBitrix(User $actor, User $user, bool $active): void
    {
        if (! $user->bitrix_user_id) {
            return;
        }

        $this->ensureSignedInWithBitrix($actor);
        $this->bitrixUsers->setActive($actor, $user->bitrix_user_id, $active);
        $user->bitrix_synced_at = now();
    }

    /** Bitrix requires the right to invite or edit users: the employee's own token. */
    private function ensureSignedInWithBitrix(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }

    /** A Bitrix employee needs a department: the top of the company structure (they can be moved later). */
    private function rootDepartmentId(): int
    {
        foreach ($this->portals->current()->departments ?? [] as $department) {
            if (empty($department['PARENT'])) {
                return (int) $department['ID'];
            }
        }

        throw BitrixException::cannotSync('The Bitrix24 company structure is unknown; reinstall the app to load it.');
    }
}
