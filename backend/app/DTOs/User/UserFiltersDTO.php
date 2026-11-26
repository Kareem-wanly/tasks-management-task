<?php

declare(strict_types=1);

namespace App\DTOs\User;

use App\DTOs\Concerns\ReadsInput;

/**
 * Filters of the user list. Null means "not filtered".
 */
final readonly class UserFiltersDTO
{
    use ReadsInput;

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
            search: empty($data['search']) ? null : self::textOrNull($data['search']),
            role: empty($data['role']) ? null : self::textOrNull($data['role']),
        );
    }
}
