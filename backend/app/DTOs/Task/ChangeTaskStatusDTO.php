<?php

declare(strict_types=1);

namespace App\DTOs\Task;

final readonly class ChangeTaskStatusDTO
{
    public function __construct(
        /** A task_statuses key. */
        public string $status,
    ) {}

    /**
     * @param  array{status: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(status: (string) $data['status']);
    }
}
