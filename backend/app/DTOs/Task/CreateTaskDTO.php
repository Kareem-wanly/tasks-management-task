<?php

declare(strict_types=1);

namespace App\DTOs\Task;

use App\DTOs\Concerns\TracksProvidedFields;

/**
 * A new task. Fields the client did not send keep their database defaults
 * (status, priority...), so only the sent ones become attributes.
 */
final readonly class CreateTaskDTO
{
    use TracksProvidedFields;

    /**
     * @param  list<string>  $provided  The input names the client sent.
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $status = null,
        public ?string $priority = null,
        public ?string $dueDate = null,
        public ?int $assignedTo = null,
        public ?int $parentId = null,
        public ?bool $allowTimeTracking = null,
        public ?int $timeEstimate = null,
        public array $provided = ['title'],
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated input: only the keys the client sent.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: self::requiredText($data['title']),
            description: self::textOrNull($data['description'] ?? null),
            status: self::textOrNull($data['status'] ?? null),
            priority: self::textOrNull($data['priority'] ?? null),
            dueDate: self::textOrNull($data['due_date'] ?? null),
            assignedTo: self::intOrNull($data['assigned_to'] ?? null),
            parentId: self::intOrNull($data['parent_id'] ?? null),
            allowTimeTracking: self::boolOrNull($data['allow_time_tracking'] ?? null),
            timeEstimate: self::intOrNull($data['time_estimate'] ?? null),
            provided: array_keys($data),
        );
    }

    /**
     * @return array<string, mixed> The sent fields as Task attributes.
     */
    public function toArray(): array
    {
        return $this->onlyProvided([
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->dueDate,
            'assigned_to' => $this->assignedTo,
            'parent_id' => $this->parentId,
            'allow_time_tracking' => $this->allowTimeTracking,
            'time_estimate' => $this->timeEstimate,
        ]);
    }
}
