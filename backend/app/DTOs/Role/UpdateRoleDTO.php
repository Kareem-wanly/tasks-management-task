<?php

declare(strict_types=1);

namespace App\DTOs\Role;

use App\DTOs\Concerns\TracksProvidedFields;

/**
 * The role fields a client changes; fields it did not send stay as they are.
 * Sending `permissions` replaces the role's permissions (null or [] clears them).
 */
final readonly class UpdateRoleDTO
{
    use TracksProvidedFields;

    /**
     * @param  list<int>  $permissionIds
     * @param  list<string>  $provided  The input names the client sent.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $displayName = null,
        public ?string $description = null,
        public array $permissionIds = [],
        public array $provided = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated input: only the keys the client sent.
     */
    public static function fromArray(array $data): self
    {
        /** @var list<int|string> $permissions */
        $permissions = $data['permissions'] ?? [];

        return new self(
            name: self::stringOrNull($data['name'] ?? null),
            displayName: self::stringOrNull($data['display_name'] ?? null),
            description: self::stringOrNull($data['description'] ?? null),
            permissionIds: array_map('intval', $permissions),
            provided: array_keys($data),
        );
    }

    /**
     * @return array<string, mixed> The sent fields as Role attributes (permissions apart).
     */
    public function toArray(): array
    {
        return $this->onlyProvided([
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
        ]);
    }
}
