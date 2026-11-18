<?php

namespace App\Jobs\Bitrix;

use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use Throwable;

/**
 * Shared by the import jobs: progress lives in bitrix_sync_cursors under
 * the job's ENTITY.
 */
trait TracksSyncProgress
{
    /** A failed import is re-run as a whole (it is idempotent), never retried mid-way. */
    public int $tries = 1;

    /** A full portal import can take far longer than the default 60 seconds. */
    public int $timeout = 3600;

    public function failed(?Throwable $exception): void
    {
        app(BitrixSyncCursorRepositoryInterface::class)->fail(self::ENTITY, $exception?->getMessage() ?: 'Unknown error');
    }
}
