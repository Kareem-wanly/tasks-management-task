<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\DTOs\Auth\UpdatePasswordDTO;
use App\DTOs\Auth\UpdateProfileDTO;
use App\Models\User;

interface AuthServiceInterface
{
    /**
     * @return array{0: User, 1: string} The user and their API token.
     */
    public function register(RegisterDTO $data): array;

    /** @return array{0: User, 1: string} The user and their API token. */
    public function login(LoginDTO $credentials): array;

    /** The signed-in user with their roles. */
    public function profile(User $user): User;

    public function logout(User $user): void;

    public function updateProfile(User $user, UpdateProfileDTO $data): User;

    public function updatePassword(User $user, UpdatePasswordDTO $data): void;
}
