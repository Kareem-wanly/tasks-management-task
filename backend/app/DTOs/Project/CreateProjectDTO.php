<?php

declare(strict_types=1);

namespace App\DTOs\Project;

use App\DTOs\Concerns\ReadsInput;
use App\Enums\ProjectStatus;

final readonly class CreateProjectDTO
{
    use ReadsInput;

    public function __construct(
        public string $title,
        public ?string $description = null,
        public ProjectStatus $status = ProjectStatus::Active,
        public ?string $startDate = null,
        public ?string $dueDate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated input.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: self::requiredText($data['title']),
            description: self::textOrNull($data['description'] ?? null),
            status: isset($data['status']) ? ProjectStatus::from(self::requiredText($data['status'])) : ProjectStatus::Active,
            startDate: self::textOrNull($data['start_date'] ?? null),
            dueDate: self::textOrNull($data['due_date'] ?? null),
        );
    }
}
