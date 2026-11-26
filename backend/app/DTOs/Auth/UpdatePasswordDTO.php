<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use SensitiveParameter;

/**
 * The new password; the current one is checked by UpdatePasswordRequest.
 */
final readonly class UpdatePasswordDTO
{
    public function __construct(
        #[SensitiveParameter] public string $password,
    ) {}

    /**
     * @param  array{password: string}  $data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(password: $data['password']);
    }
}
