<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use App\Enums\SyncEntityState;

/** Step 5.1: the sync status page, as the service hands it to the controller. */
final readonly class BitrixSyncStatusDTO
{
    /**
     * @param  list<BitrixSyncEntityDTO>  $entities  In import order; the changes sync last.
     * @param  int  $failedJobs  Jobs of the bitrix queue that failed and wait in failed_jobs.
     * @param  int  $conflicts  Our changes Bitrix answered with something else, in the last day.
     * @param  int  $unconfirmed  Our changes Bitrix never echoed back, in the last day.
     */
    public function __construct(
        public array $entities,
        public int $failedJobs,
        public int $conflicts,
        public int $unconfirmed,
    ) {}

    public function isRunning(): bool
    {
        foreach ($this->entities as $entity) {
            if ($entity->state === SyncEntityState::Running) {
                return true;
            }
        }

        return false;
    }
}
