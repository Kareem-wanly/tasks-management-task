<?php

declare(strict_types=1);

namespace App\DTOs\Task;

use App\DTOs\Concerns\ReadsInput;

/**
 * Filters and sort of the task list (GET /tasks). Null means "not filtered".
 */
final readonly class TaskFiltersDTO
{
    use ReadsInput;

    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        /** true: any closing status; false: any open one. */
        public ?bool $finished = null,
        public ?string $priority = null,
        public ?int $assignedTo = null,
        public ?int $projectId = null,
        public ?string $dueDateFrom = null,
        public ?string $dueDateTo = null,
        public bool $overdue = false,
        public ?string $sortBy = null,
        public ?string $sortOrder = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            search: self::filledTextOrNull($data['search'] ?? null),
            status: self::filledTextOrNull($data['status'] ?? null),
            finished: isset($data['finished']) ? (bool) $data['finished'] : null,
            priority: self::filledTextOrNull($data['priority'] ?? null),
            assignedTo: self::intOrNull($data['assigned_to'] ?? null),
            projectId: self::intOrNull($data['project_id'] ?? null),
            dueDateFrom: self::filledTextOrNull($data['due_date_from'] ?? null),
            dueDateTo: self::filledTextOrNull($data['due_date_to'] ?? null),
            overdue: (bool) ($data['overdue'] ?? false),
            sortBy: self::filledTextOrNull($data['sort_by'] ?? null),
            sortOrder: self::filledTextOrNull($data['sort_order'] ?? null),
        );
    }
}
