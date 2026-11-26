<?php

declare(strict_types=1);

namespace App\DTOs\Task;

final readonly class TaskResultDTO
{
    public function __construct(
        public string $text,
    ) {}

    /**
     * @param  array{text: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(text: trim($data['text']));
    }
}
