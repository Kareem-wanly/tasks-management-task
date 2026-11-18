<?php

declare(strict_types=1);

namespace App\DTOs\Task;

final readonly class MoveChecklistItemDTO
{
    public function __construct(
        /** The sibling it goes after; null: first. */
        public ?int $afterId,
    ) {}

    /**
     * @param  array{after_id: int|string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(afterId: $data['after_id'] === null ? null : (int) $data['after_id']);
    }
}
