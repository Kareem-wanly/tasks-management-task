<?php

declare(strict_types=1);

namespace App\DTOs\Project;

final readonly class AddProjectMemberDTO
{
    public function __construct(
        public int $userId,
        /** member or moderator. */
        public string $role = 'member',
    ) {}

    /**
     * @param  array{user_id: int|string, role?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            role: $data['role'] ?? 'member',
        );
    }
}
