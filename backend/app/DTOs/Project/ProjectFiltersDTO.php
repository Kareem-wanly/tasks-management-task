<?php

declare(strict_types=1);

namespace App\DTOs\Project;

use App\DTOs\Concerns\ReadsInput;

/**
 * Filters and sort of the project list. Null means "not filtered".
 */
final readonly class ProjectFiltersDTO
{
    use ReadsInput;

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
            search: self::filledTextOrNull($data['search'] ?? null),
            status: self::filledTextOrNull($data['status'] ?? null),
            memberId: empty($data['member_id']) ? null : self::intOrNull($data['member_id']),
            sort: self::filledTextOrNull($data['sort'] ?? null),
            direction: self::filledTextOrNull($data['direction'] ?? null),
        );
    }
}
