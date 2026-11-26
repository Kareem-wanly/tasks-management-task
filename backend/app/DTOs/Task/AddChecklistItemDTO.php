<?php

declare(strict_types=1);

namespace App\DTOs\Task;

final readonly class AddChecklistItemDTO
{
    public function __construct(
        public string $title,
        /** The list it goes in; null: the task's default list. */
        public ?int $listId = null,
    ) {}

    /**
     * @param  array{title: string, list_id?: int|string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: trim($data['title']),
            listId: empty($data['list_id']) ? null : (int) $data['list_id'],
        );
    }
}
