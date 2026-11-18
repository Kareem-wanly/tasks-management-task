<?php

declare(strict_types=1);

namespace App\DTOs\User;

final readonly class SetUserActiveDTO
{
    public function __construct(
        public bool $active,
    ) {}

    /**
     * @param  array{active: bool|int|string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(active: filter_var($data['active'], FILTER_VALIDATE_BOOLEAN));
    }
}
