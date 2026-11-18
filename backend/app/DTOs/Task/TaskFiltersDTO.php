<?php

declare(strict_types=1);

namespace App\DTOs\Task;

/**
 * Filters and sort of the task list (GET /tasks). Null means "not filtered".
 */
final readonly class TaskFiltersDTO
{
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
            search: self::text($data['search'] ?? null),
            status: self::text($data['status'] ?? null),
            finished: isset($data['finished']) ? (bool) $data['finished'] : null,
            priority: self::text($data['priority'] ?? null),
            assignedTo: isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            projectId: isset($data['project_id']) ? (int) $data['project_id'] : null,
            dueDateFrom: self::text($data['due_date_from'] ?? null),
            dueDateTo: self::text($data['due_date_to'] ?? null),
            overdue: (bool) ($data['overdue'] ?? false),
            sortBy: self::text($data['sort_by'] ?? null),
            sortOrder: self::text($data['sort_order'] ?? null),
        );
    }

    /** An empty value filters nothing, as before. */
    private static function text(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
