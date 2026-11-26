<?php

declare(strict_types=1);

namespace App\DTOs\User;

/**
 * Filters of the user list. Null means "not filtered".
 */
final readonly class UserFiltersDTO
{
    public function __construct(
        public ?string $search = null,
        /** A role name or ID. */
        public ?string $role = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated input.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            search: empty($data['search']) ? null : (string) $data['search'],
            role: empty($data['role']) ? null : (string) $data['role'],
        );
    }
}
