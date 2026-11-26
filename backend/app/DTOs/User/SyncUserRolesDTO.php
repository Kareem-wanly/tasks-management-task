<?php

declare(strict_types=1);

namespace App\DTOs\User;

/**
 * The full list of a user's roles (replaces the old one).
 */
final readonly class SyncUserRolesDTO
{
    /**
     * @param  list<int>  $roleIds
     */
    public function __construct(
        public array $roleIds,
    ) {}

    /**
     * @param  array{roles: list<int|string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(roleIds: array_map('intval', $data['roles']));
    }
}
