<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Integrations\Bitrix24\BitrixUserPusher;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Employees. Changes go to Bitrix24 first (BitrixUserPusher) and are saved
 * here only when Bitrix accepts them.
 */
class UserService
{
    /** Dropdowns never list more than this many people. */
    private const OPTIONS_LIMIT = 1000;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly BitrixUserPusher $bitrix,
    ) {}

    /** @param  array{search?: ?string, role?: ?string}  $filters */
    public function list(array $filters, int $perPage): LengthAwarePaginator
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
     *
     * @param  array{name: string, email: string, password: string, role_id?: ?int}  $data
     */
    public function invite(User $actor, array $data): User
    {
        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $this->bitrix->invite($actor, $user);

        DB::transaction(function () use ($user, $data) {
            $this->users->save($user);
            $this->users->attachRole($user, ! empty($data['role_id']) ? (int) $data['role_id'] : $this->roles->memberRole()->id);
        });

        return $this->users->withRoles($user);
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $actor, User $user, array $data): User
    {
        $user->fill($data);
        $this->bitrix->update($actor, $user);
        $this->users->save($user);

        return $user;
    }

    /**
     * Replaces deleting: Bitrix24 has no user delete over REST, so employees
     * are deactivated (and can be activated again). A deactivated employee
     * can't sign in, and their open sessions end.
     */
    public function setActive(User $actor, User $user, bool $active): User
    {
        if ($user->is($actor)) {
            throw BusinessRuleException::forbidden('You cannot deactivate your own account.');
        }
        if ($user->hasRole('Administrator') && ! $actor->hasRole('Administrator')) {
            throw BusinessRuleException::forbidden('Only an Administrator can change another Administrator.');
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
