<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

/**
 * An event Bitrix24 posted to /api/bitrix/events (already checked to come from our portal).
 */
final readonly class BitrixEventDTO
{
    /**
     * @param  array<string, mixed>  $data  The event's data; it names the record that changed.
     */
    public function __construct(
        /** Upper case, e.g. ONTASKUPDATE. */
        public string $event,
        public array $data = [],
    ) {}

    /**
     * @param  array{event?: ?string, data?: array<string, mixed>|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            event: strtoupper((string) ($data['event'] ?? '')),
            data: $data['data'] ?? [],
        );
    }
}
