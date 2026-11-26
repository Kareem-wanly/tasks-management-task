<?php

declare(strict_types=1);

namespace App\DTOs\Task;

/**
 * The full lists of a task's accomplices and auditors (each replaces the old one).
 */
final readonly class TaskMembersDTO
{
    /**
     * @param  list<int>  $accompliceIds
     * @param  list<int>  $auditorIds
     */
    public function __construct(
        public array $accompliceIds,
        public array $auditorIds,
    ) {}

    /**
     * @param  array{accomplice_ids: list<int|string>, auditor_ids: list<int|string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accompliceIds: array_map('intval', $data['accomplice_ids']),
            auditorIds: array_map('intval', $data['auditor_ids']),
        );
    }
}
