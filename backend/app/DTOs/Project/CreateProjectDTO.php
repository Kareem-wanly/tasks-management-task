<?php

declare(strict_types=1);

namespace App\DTOs\Project;

final readonly class CreateProjectDTO
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public string $status = 'active',
        public ?string $startDate = null,
        public ?string $dueDate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated input.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: (string) $data['title'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            status: isset($data['status']) ? (string) $data['status'] : 'active',
            startDate: isset($data['start_date']) ? (string) $data['start_date'] : null,
            dueDate: isset($data['due_date']) ? (string) $data['due_date'] : null,
        );
    }
}
