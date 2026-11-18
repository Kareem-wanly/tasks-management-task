<?php

declare(strict_types=1);

namespace App\DTOs\Project;

/**
 * Filters and sort of the project list. Null means "not filtered".
 */
final readonly class ProjectFiltersDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?int $memberId = null,
        public ?string $sort = null,
        public ?string $direction = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated input.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            search: self::text($data['search'] ?? null),
            status: self::text($data['status'] ?? null),
            memberId: empty($data['member_id']) ? null : (int) $data['member_id'],
            sort: self::text($data['sort'] ?? null),
            direction: self::text($data['direction'] ?? null),
        );
    }

    /** An empty value filters nothing, as before. */
    private static function text(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
