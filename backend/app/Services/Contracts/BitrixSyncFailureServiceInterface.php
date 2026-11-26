<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\BitrixSyncOperation;
use App\Exceptions\Bitrix\BitrixSyncFailureClosedException;
use App\Models\BitrixSyncFailure;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Step 5.2: dead letters. Background Bitrix24 work (event syncs, imports)
 * that failed after all its tries is kept here until it succeeds again or
 * someone dismisses it, so no failure goes by silently.
 */
interface BitrixSyncFailureServiceInterface
{
    /**
     * What a failure says on the status page and in the log: the message of
     * our own exceptions, only the class of any other (SEC-5).
     */
    public function describe(?Throwable $exception): string;

    /**
     * The operation failed after all its tries: opens a failure, or counts
     * one more attempt on the one already open.
     */
    public function record(BitrixSyncOperation $operation, string $subject, ?Throwable $exception, ?string $failedJobUuid): void;

    /**
     * An outer job failed because of an inner failure already recorded: its
     * failed_jobs row becomes the open failure's (no extra attempt). Records
     * the failure when nothing inside did.
     */
    public function attachFailedJob(BitrixSyncOperation $operation, string $subject, ?Throwable $exception, ?string $failedJobUuid): void;

    /** The operation succeeded: closes its open failure, if any. */
    public function resolve(BitrixSyncOperation $operation, string $subject): void;

    /**
     * The open failures, most recent first (at most MAX_LISTED).
     *
     * @return Collection<int, BitrixSyncFailure>
     */
    public function open(): Collection;

    public function countOpen(): int;

    /**
     * Marks the failure as queued again; the caller queues the work.
     *
     * @throws BitrixSyncFailureClosedException When it is closed, or a retry was queued moments ago.
     */
    public function markRetrying(BitrixSyncFailure $failure): BitrixSyncFailure;

    /**
     * Closes the failure without a retry.
     *
     * @throws BitrixSyncFailureClosedException When it is already closed.
     */
    public function dismiss(BitrixSyncFailure $failure): void;
}
