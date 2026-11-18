<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\DTOs\Auth\UpdatePasswordDTO;
use App\DTOs\Auth\UpdateProfileDTO;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Password sign-up, sign-in and the user's own profile. (Bitrix24 sign-in
 * is BitrixAuthService.)
 */
class AuthService
{
    private const TOKEN_NAME = 'auth_token';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * @return array{0: User, 1: string} The user and their API token.
     */
    public function register(RegisterDTO $data): array
    {
        $user = DB::transaction(function () use ($data): User {
            $user = $this->users->create(['name' => $data->name, 'email' => $data->email, 'password' => $data->password]);

            if (($member = $this->roles->findByName('Member')) instanceof \App\Models\Role) {
                $this->users->attachRole($user, $member->id);
            }

            return $user;
        });

        return [$this->users->withRoles($user), $this->users->createApiToken($user, self::TOKEN_NAME)];
    }

    /** @return array{0: User, 1: string} The user and their API token. */
    public function login(LoginDTO $credentials): array
    {
        $user = $this->users->findByEmail($credentials->email);

        if (! $user instanceof User || ! Hash::check($credentials->password, $user->password)) {
            throw new BusinessRuleException('Invalid login credentials', 401);
        }

        if (! $user->is_active) {
            throw BusinessRuleException::forbidden('This account is deactivated');
        }

        return [$this->users->withRoles($user), $this->users->createApiToken($user, self::TOKEN_NAME)];
    }

    /** The signed-in user with their roles. */
    public function profile(User $user): User
    {
        return $this->users->withRoles($user);
    }

    public function logout(User $user): void
    {
        $this->users->revokeCurrentToken($user);
    }

    public function updateProfile(User $user, UpdateProfileDTO $data): User
    {
        $this->users->update($user, $data->toArray());

        return $user;
    }

    public function updatePassword(User $user, UpdatePasswordDTO $data): void
    {
        $this->users->update($user, ['password' => $data->password]);
    }
}
