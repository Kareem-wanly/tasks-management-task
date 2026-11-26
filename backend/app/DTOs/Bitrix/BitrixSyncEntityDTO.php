<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use App\Enums\SyncEntityState;
use Carbon\CarbonImmutable;

/** Step 5.1: where the import of one entity (users, tasks, ...) stands. */
final readonly class BitrixSyncEntityDTO
{
    public function __construct(
        public string $entity,
        public SyncEntityState $state,
        public int $processed,
        public int $total,
        public ?string $error,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $lastSyncedAt,
    ) {}
}
