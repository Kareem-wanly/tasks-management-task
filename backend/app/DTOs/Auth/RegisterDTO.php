<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use SensitiveParameter;

final readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        #[SensitiveParameter] public string $password,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(name: $data['name'], email: $data['email'], password: $data['password']);
    }
}
