<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\BitrixSyncOperation;
use App\Services\Contracts\BitrixSyncFailureServiceInterface;
use Throwable;

/**
 * Step 5.2, shared by the event jobs: a job that failed after all its tries
 * becomes a dead letter on the sync status page, with "Retry". The service
 * that runs the job closes it once the same work succeeds.
 */
trait RecordsSyncFailure
{
    public function failed(?Throwable $exception): void
    {
        // The worker calls failed() with the exception only, so the service is resolved here (C.4: documented).
        resolve(BitrixSyncFailureServiceInterface::class)->record(
            $this->failureOperation(),
            $this->failureSubject(),
            $exception,
            $this->job?->uuid(),
        );
    }

    abstract protected function failureOperation(): BitrixSyncOperation;

    abstract protected function failureSubject(): string;
}
