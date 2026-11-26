<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Bitrix\BitrixSyncStatusDTO;
use App\DTOs\Bitrix\StartBitrixSyncDTO;
use App\Exceptions\Bitrix\BitrixSyncAlreadyRunningException;

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
}
