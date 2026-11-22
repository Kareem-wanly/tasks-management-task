<?php

declare(strict_types=1);

namespace App\DTOs\TaskLookup;

use App\DTOs\Concerns\TracksProvidedFields;

/**
 * A task status or priority, new or changed. Fields the client did not send
 * keep their defaults (new) or current values (update).
 */
final readonly class SaveTaskLookupDTO
{
    use TracksProvidedFields;

    /**
     * @param  list<string>  $provided  The input names the client sent.
     */
    public function __construct(
        public ?string $key = null,
        public ?string $name = null,
        public ?string $color = null,
        public ?int $sort = null,
        public ?int $bitrixId = null,
        /** Statuses only: the status closes the task. */
        public ?bool $isFinal = null,
        public array $provided = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated input: only the keys the client sent.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: self::textOrNull($data['key'] ?? null),
            name: self::textOrNull($data['name'] ?? null),
            color: self::textOrNull($data['color'] ?? null),
            sort: self::intOrNull($data['sort'] ?? null),
            bitrixId: self::intOrNull($data['bitrix_id'] ?? null),
            isFinal: self::boolOrNull($data['is_final'] ?? null),
            provided: array_keys($data),
        );
    }

    /**
     * @return array<string, mixed> The sent fields as TaskLookup attributes.
     */
    public function toArray(): array
    {
        return $this->onlyProvided([
            'key' => $this->key,
            'name' => $this->name,
            'color' => $this->color,
            'sort' => $this->sort,
            'bitrix_id' => $this->bitrixId,
            'is_final' => $this->isFinal,
        ]);
    }
}
