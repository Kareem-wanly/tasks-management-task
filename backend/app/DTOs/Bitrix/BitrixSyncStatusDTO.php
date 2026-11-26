<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use App\Enums\SyncEntityState;

/** Step 5.1: the sync status page, as the service hands it to the controller. */
final readonly class BitrixSyncStatusDTO
{
    /**
     * @param  list<BitrixSyncEntityDTO>  $entities  In import order; the changes sync last.
     * @param  int  $failures  Open dead letters: background work that failed after all its tries (step 5.2).
     * @param  int  $conflicts  Our changes Bitrix answered with something else, in the last day.
     * @param  int  $unconfirmed  Our changes Bitrix never echoed back, in the last day.
     * @param  list<BitrixReconciliationSummaryDTO>  $reconciliation  The nightly comparison of users, projects and tasks (step 5.3).
     */
    public function __construct(
        public array $entities,
        public int $failures,
        public int $conflicts,
        public int $unconfirmed,
        public array $reconciliation = [],
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
