<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use App\Enums\ReconciliationNightState;
use App\Models\BitrixReconciliation;

/** Step 5.3: how the nightly reconciliation of one entity stands, for the status page. */
final readonly class BitrixReconciliationSummaryDTO
{
    /**
     * @param  BitrixReconciliation|null  $latest  Null until the first full import of the entity.
     * @param  int  $cleanNights  Days in a row, up to today (or yesterday while today is unchecked), whose checks all matched.
     * @param  list<array{date: string, state: ReconciliationNightState}>  $nights  The last days, oldest first, ending today.
     */
    public function __construct(
        public string $entity,
        public ?BitrixReconciliation $latest,
        public int $cleanNights,
        public array $nights,
    ) {}
}
