<?php

declare(strict_types=1);

namespace App\DTOs\Role;

final readonly class CreateRoleDTO
{
    /**
     * @param  list<int>  $permissionIds
     */
    public function __construct(
        public string $name,
        /** Null: the name. */
        public ?string $displayName = null,
        public ?string $description = null,
        public array $permissionIds = [],
    ) {}

    /**
     * @param  array{name: string, display_name?: ?string, description?: ?string, permissions?: list<int|string>|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            displayName: $data['display_name'] ?? null,
            description: $data['description'] ?? null,
            permissionIds: array_map('intval', $data['permissions'] ?? []),
        );
    }
}
