<?php

declare(strict_types=1);

namespace App\DTOs\Role;

/**
 * The full list of a role's permissions (replaces the old one).
 */
final readonly class SyncRolePermissionsDTO
{
    /**
     * @param  list<int>  $permissionIds
     */
    public function __construct(
        public array $permissionIds,
    ) {}

    /**
     * @param  array{permissions: list<int|string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(permissionIds: array_map('intval', $data['permissions']));
    }
}
