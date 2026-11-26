<?php

declare(strict_types=1);

namespace App\DTOs\User;

use App\DTOs\Concerns\TracksProvidedFields;

/**
 * The account fields an administrator changes; fields not sent stay as they are.
 */
final readonly class UpdateUserDTO
{
    use TracksProvidedFields;

    /**
     * @param  list<string>  $provided  The input names the client sent.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public array $provided = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated input: only the keys the client sent.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: self::stringOrNull($data['name'] ?? null),
            email: self::stringOrNull($data['email'] ?? null),
            provided: array_keys($data),
        );
    }

    /**
     * @return array<string, mixed> The sent fields as User attributes.
     */
    public function toArray(): array
    {
        return $this->onlyProvided(['name' => $this->name, 'email' => $this->email]);
    }
}
