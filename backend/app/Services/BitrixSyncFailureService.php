<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BitrixSyncFailureStatus;
use App\Enums\BitrixSyncOperation;
use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\Bitrix\BitrixSyncFailureClosedException;
use App\Exceptions\BusinessException;
use App\Models\BitrixSyncFailure;
use App\Repositories\Contracts\BitrixSyncFailureRepositoryInterface;
use App\Repositories\Contracts\FailedJobRepositoryInterface;
use App\Services\Contracts\BitrixSyncFailureServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class BitrixSyncFailureService implements BitrixSyncFailureServiceInterface
{
    /** The status page lists this many; the count shows the rest exist. */
    public const MAX_LISTED = 100;

    /** Errors are kept short: the page shows them, the log has the rest. */
    public const MAX_ERROR_LENGTH = 1000;

    public function __construct(
        private BitrixSyncFailureRepositoryInterface $failures,
        private FailedJobRepositoryInterface $failedJobs,
    ) {}

    public function describe(?Throwable $exception): string
    {
        if (! $exception instanceof Throwable) {
            return 'Unknown error';
        }

        // SEC-5: Bitrix errors keep their technical text with tokens and secrets cut out (the
        // user-facing one speaks of "your change", wrong for background work); our other
        // exceptions are written for people; anything else (a QueryException quotes data) shows its class only.
        $message = match (true) {
            $exception instanceof BitrixException => BitrixException::redactSecrets($exception->getMessage()),
            $exception instanceof BusinessException => $exception->publicMessage(),
            default => sprintf('Internal error (%s). See the application log.', class_basename($exception)),
        };

        return mb_strimwidth($message === '' ? 'Unknown error' : $message, 0, self::MAX_ERROR_LENGTH, '…');
    }

    public function record(BitrixSyncOperation $operation, string $subject, ?Throwable $exception, ?string $failedJobUuid): void
    {
        $error = $this->describe($exception);
        Log::warning('Bitrix24 background sync failed.', ['operation' => $operation->value, 'subject' => $subject, 'error' => $error]);

        $attributes = [
            'error' => $error,
            'status' => BitrixSyncFailureStatus::Pending,
            'failed_job_uuid' => $failedJobUuid,
            'last_failed_at' => CarbonImmutable::now(),
        ];

        $open = $this->failures->findOpen($operation, $subject);
        if (! $open instanceof BitrixSyncFailure) {
            $this->failures->create(['operation' => $operation, 'subject' => $subject, 'attempts' => 1, ...$attributes]);

            return;
        }

        // The newer failed_jobs row replaces the older one.
        $this->forgetFailedJob($open);
        $this->failures->update($open, ['attempts' => $open->attempts + 1, ...$attributes]);
    }

    public function attachFailedJob(BitrixSyncOperation $operation, string $subject, ?Throwable $exception, ?string $failedJobUuid): void
    {
        $open = $this->failures->findOpen($operation, $subject);
        if (! $open instanceof BitrixSyncFailure) {
            // Nothing inside recorded it (e.g. the run was killed): this is the only trace.
            $this->record($operation, $subject, $exception, $failedJobUuid);

            return;
        }

        if ($failedJobUuid !== null && $failedJobUuid !== $open->failed_job_uuid) {
            $this->forgetFailedJob($open);
            $this->failures->update($open, ['failed_job_uuid' => $failedJobUuid]);
        }
    }

    public function resolve(BitrixSyncOperation $operation, string $subject): void
    {
        $open = $this->failures->findOpen($operation, $subject);
        if ($open instanceof BitrixSyncFailure) {
            $this->close($open, BitrixSyncFailureStatus::Resolved);
        }
    }

    public function open(): Collection
    {
        return $this->failures->open(self::MAX_LISTED);
    }

    public function countOpen(): int
    {
        return $this->failures->countOpen();
    }

    public function markRetrying(BitrixSyncFailure $failure): BitrixSyncFailure
    {
        if (! $failure->status->isOpen()) {
            throw BitrixSyncFailureClosedException::closed();
        }
        if (! $failure->canRetry()) {
            throw BitrixSyncFailureClosedException::retryQueued();
        }

        // Queued again by us: a later `queue:retry all` must not run it a second time.
        $this->forgetFailedJob($failure);

        return $this->failures->update($failure, [
            'status' => BitrixSyncFailureStatus::Retrying,
            'retried_at' => CarbonImmutable::now(),
            'failed_job_uuid' => null,
        ]);
    }

    public function dismiss(BitrixSyncFailure $failure): void
    {
        if (! $failure->status->isOpen()) {
            throw BitrixSyncFailureClosedException::closed();
        }

        $this->close($failure, BitrixSyncFailureStatus::Dismissed);
    }

    private function close(BitrixSyncFailure $failure, BitrixSyncFailureStatus $status): void
    {
        $this->forgetFailedJob($failure);
        $this->failures->update($failure, ['status' => $status, 'closed_at' => CarbonImmutable::now(), 'failed_job_uuid' => null]);
    }

    private function forgetFailedJob(BitrixSyncFailure $failure): void
    {
        if ($failure->failed_job_uuid !== null) {
            $this->failedJobs->forget($failure->failed_job_uuid);
        }
    }
}
