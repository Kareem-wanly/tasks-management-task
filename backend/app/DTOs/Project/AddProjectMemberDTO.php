<?php

declare(strict_types=1);

namespace App\DTOs\Project;

use App\Enums\ProjectMemberRole;

final readonly class AddProjectMemberDTO
{
    public function __construct(
        public int $userId,
        /** member or moderator. */
        public ProjectMemberRole $role = ProjectMemberRole::Member,
    ) {}

    /**
     * @param  array{user_id: int|string, role?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            role: isset($data['role']) ? ProjectMemberRole::from($data['role']) : ProjectMemberRole::Member,
        );
    }
}
