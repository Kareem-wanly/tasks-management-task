<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class UpdateProfileDTO
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    /**
     * @param  array{name: string, email: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(name: $data['name'], email: $data['email']);
    }

    /**
     * @return array{name: string, email: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'email' => $this->email];
    }
}
