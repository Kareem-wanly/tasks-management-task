<?php

declare(strict_types=1);

namespace App\DTOs\User;

use SensitiveParameter;

final readonly class InviteUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        #[SensitiveParameter] public string $password,
        /** Null: the Member role. */
        public ?int $roleId = null,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, role_id?: int|string|null}  $data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            roleId: empty($data['role_id']) ? null : (int) $data['role_id'],
        );
    }
}
