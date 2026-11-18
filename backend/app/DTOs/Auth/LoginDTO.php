<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use SensitiveParameter;

final readonly class LoginDTO
{
    public function __construct(
        public string $email,
        #[SensitiveParameter] public string $password,
    ) {}

    /**
     * @param  array{email: string, password: string}  $data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(email: $data['email'], password: $data['password']);
    }
}
