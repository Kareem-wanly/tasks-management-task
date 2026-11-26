<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Services\Contracts\BitrixImportServiceInterface;
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
        // The worker calls failed() with the exception only, so the service is resolved here (C.4: documented).
        resolve(BitrixImportServiceInterface::class)->markFailed($this->cursorEntity(), $exception?->getMessage() ?: 'Unknown error');
    }

    /** The bitrix_sync_cursors row this run reports to. */
    protected function cursorEntity(): string
    {
        return self::ENTITY;
    }
}
