<?php

declare(strict_types=1);

namespace App\DTOs\Task;

final readonly class DelegateTaskDTO
{
    public function __construct(
        /** The new responsible person. */
        public int $userId,
    ) {}

    /**
     * @param  array{user_id: int|string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(userId: (int) $data['user_id']);
    }
}
