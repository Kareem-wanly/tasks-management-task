<?php

namespace App\Services;

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
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{0: User, 1: string} The user and their API token.
     */
    public function register(array $data): array
    {
        $user = DB::transaction(function () use ($data) {
            $user = $this->users->create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);

            if ($member = $this->roles->findByName('Member')) {
                $this->users->attachRole($user, $member->id);
            }

            return $user;
        });

        return [$this->users->withRoles($user), $this->users->createApiToken($user, self::TOKEN_NAME)];
    }

    /** @return array{0: User, 1: string} The user and their API token. */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
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

    /** @param  array{name: string, email: string}  $data */
    public function updateProfile(User $user, array $data): User
    {
        $this->users->update($user, $data);

        return $user;
    }

    public function updatePassword(User $user, string $password): void
    {
        $this->users->update($user, ['password' => $password]);
    }
}
