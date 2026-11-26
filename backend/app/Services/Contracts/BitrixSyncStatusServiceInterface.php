<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Bitrix\BitrixSyncStatusDTO;
use App\DTOs\Bitrix\StartBitrixSyncDTO;
use App\Exceptions\Bitrix\BitrixSyncAlreadyRunningException;
use App\Exceptions\Bitrix\BitrixSyncFailureClosedException;
use App\Models\BitrixSyncFailure;

/**
 * Step 5.1: the sync status page. Shows how the Bitrix24 sync stands and
 * starts one on request.
 */
interface BitrixSyncStatusServiceInterface
{
    public function status(): BitrixSyncStatusDTO;

    /**
     * Queues a sync on the bitrix queue and answers the status after it.
     *
     * @throws BitrixSyncAlreadyRunningException
     */
    public function start(StartBitrixSyncDTO $request): BitrixSyncStatusDTO;

    /**
     * Step 5.2: queues the failed work again (an import with the passes after
     * it); the failure closes once that work succeeds.
     *
     * @throws BitrixSyncFailureClosedException
     * @throws BitrixSyncAlreadyRunningException An import retry while a sync runs or waits in the queue.
     */
    public function retry(BitrixSyncFailure $failure): BitrixSyncFailure;
}
