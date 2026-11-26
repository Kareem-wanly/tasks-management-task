<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Services\Contracts\BitrixImportServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Step 5.1: the "sync changes now" button. Does what bitrix:sync-changes
 * does every 5 minutes, on the bitrix queue. Unique, so pressing twice
 * queues one run.
 */
final class SyncBitrixChangesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** The next scheduled run does the same work: no retry. */
    public int $tries = 1;

    public int $backoff = 0;

    /** As the import jobs it runs; a first sync ever imports everything. */
    public int $timeout = 3600;

    /** The lock never outlives a stuck run by much. */
    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(BitrixImportServiceInterface $import): void
    {
        $import->syncChanges();
    }

    /**
     * The pass that failed inside has already recorded it (cursor and dead
     * letter); this job's own failed_jobs row joins that dead letter, so a
     * retry or dismiss clears it too. A run killed before any pass recorded
     * anything is recorded here.
     */
    public function failed(?Throwable $exception): void
    {
        // The worker calls failed() with the exception only, so the service is resolved here (C.4: documented).
        resolve(BitrixImportServiceInterface::class)->markChangesSyncFailed($exception, $this->job?->uuid());
    }
}
