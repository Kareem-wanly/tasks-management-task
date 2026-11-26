<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Bitrix\BitrixReconciliationSummaryDTO;

/**
 * Step 5.3: the nightly reconciliation. Each full import of users,
 * projects and tasks compares the Bitrix24 IDs it listed with ours, so a
 * record skipped or not deleted shows up instead of drifting silently.
 */
interface BitrixReconciliationServiceInterface
{
    /** What is reconciled, in import order. */
    public const ENTITIES = [BitrixImportServiceInterface::USERS, BitrixImportServiceInterface::PROJECTS, BitrixImportServiceInterface::TASKS];

    /**
     * Compares and stores one check.
     *
     * @param  list<int>  $bitrixIds  What the full listing returned.
     * @param  list<int>  $localIds  What we hold, after the import.
     */
    public function record(string $entity, array $bitrixIds, array $localIds): void;

    /** @return list<BitrixReconciliationSummaryDTO> In ENTITIES order. */
    public function summary(): array;
}
