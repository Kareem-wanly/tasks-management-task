<?php

declare(strict_types=1);

namespace App\DTOs\Task;

/**
 * Time spent on a task, entered as hours and minutes.
 */
final readonly class TimeEntryDTO
{
    public function __construct(
        public int $seconds,
        public ?string $comment = null,
    ) {}

    /**
     * @param  array{hours: int|string, minutes: int|string, comment?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            seconds: (int) $data['hours'] * 3600 + (int) $data['minutes'] * 60,
            comment: self::comment($data['comment'] ?? null),
        );
    }

    /** A blank comment is no comment. */
    public static function comment(?string $comment): ?string
    {
        $comment = trim((string) $comment);

        return $comment !== '' ? $comment : null;
    }
}
