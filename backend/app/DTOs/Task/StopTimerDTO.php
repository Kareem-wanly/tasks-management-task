<?php

declare(strict_types=1);

namespace App\DTOs\Task;

final readonly class StopTimerDTO
{
    public function __construct(
        /** Saved on the time entry the timer makes. */
        public ?string $comment = null,
    ) {}

    /**
     * @param  array{comment?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(comment: TimeEntryDTO::comment($data['comment'] ?? null));
    }
}
