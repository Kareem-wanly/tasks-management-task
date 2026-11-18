<?php

declare(strict_types=1);

namespace App\DTOs\Task;

/**
 * What changes on a checklist item; null means "not sent" (neither can be cleared).
 */
final readonly class UpdateChecklistItemDTO
{
    public function __construct(
        public ?string $title = null,
        public ?bool $isComplete = null,
    ) {}

    /**
     * @param  array{title?: string, is_complete?: bool|int|string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: isset($data['title']) ? trim($data['title']) : null,
            isComplete: isset($data['is_complete']) ? filter_var($data['is_complete'], FILTER_VALIDATE_BOOLEAN) : null,
        );
    }
}
