<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BitrixSyncFailureStatus;
use App\Enums\BitrixSyncOperation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Step 5.2: background Bitrix24 work that failed after all its tries (a
 * dead letter). Stored and queried through BitrixSyncFailureRepository.
 *
 * @property int $id
 * @property BitrixSyncOperation $operation
 * @property string $subject
 * @property string $error
 * @property int $attempts
 * @property BitrixSyncFailureStatus $status
 * @property string|null $failed_job_uuid
 * @property CarbonImmutable $last_failed_at
 * @property CarbonImmutable|null $retried_at
 * @property CarbonImmutable|null $closed_at
 */
class BitrixSyncFailure extends Model
{
    /** A retry the worker never picked up (worker down) may be queued again after this. */
    public const RETRY_STALE_SECONDS = 600;

    protected $fillable = ['operation', 'subject', 'error', 'attempts', 'status', 'failed_job_uuid', 'last_failed_at', 'retried_at', 'closed_at'];

    /** Open, and not queued again moments ago. */
    public function canRetry(): bool
    {
        return match ($this->status) {
            BitrixSyncFailureStatus::Pending => true,
            BitrixSyncFailureStatus::Retrying => ! $this->retried_at instanceof CarbonImmutable
                || $this->retried_at->addSeconds(self::RETRY_STALE_SECONDS)->isPast(),
            BitrixSyncFailureStatus::Resolved, BitrixSyncFailureStatus::Dismissed => false,
        };
    }

    protected function casts(): array
    {
        return [
            'operation' => BitrixSyncOperation::class,
            'status' => BitrixSyncFailureStatus::class,
            'attempts' => 'integer',
            'last_failed_at' => 'datetime',
            'retried_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
