<?php

declare(strict_types=1);

namespace App\DTOs\Project;

use App\DTOs\Concerns\TracksProvidedFields;

/**
 * The project fields a client changes; fields it did not send stay as they are.
 */
final readonly class UpdateProjectDTO
{
    use TracksProvidedFields;

    /**
     * @param  list<string>  $provided  The input names the client sent.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $status = null,
        public ?string $startDate = null,
        public ?string $dueDate = null,
        public array $provided = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated input: only the keys the client sent.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: self::stringOrNull($data['title'] ?? null),
            description: self::stringOrNull($data['description'] ?? null),
            status: self::stringOrNull($data['status'] ?? null),
            startDate: self::stringOrNull($data['start_date'] ?? null),
            dueDate: self::stringOrNull($data['due_date'] ?? null),
            provided: array_keys($data),
        );
    }

    /**
     * @return array<string, mixed> The sent fields as Project attributes.
     */
    public function toArray(): array
    {
        return $this->onlyProvided([
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'start_date' => $this->startDate,
            'due_date' => $this->dueDate,
        ]);
    }
}
