<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\BitrixSyncOperation;
use App\Enums\QueueName;
use App\Services\Contracts\BitrixSyncServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 3.4: brings one Bitrix24 group up to date after a group event.
 * Unique until it starts, so the events Bitrix sends for one change
 * collapse into one fetch (debounce).
 */
final class SyncBitrixProjectJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable, RecordsSyncFailure;

    public int $tries = 3;

    /**
     * Seconds before each retry, e.g. while Bitrix is unreachable.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60];

    /** One group's fetch and save; well under $uniqueFor. */
    public int $timeout = 90;

    /** Safety net: the lock never outlives a stuck job by much. */
    public int $uniqueFor = 120;

    public function __construct(public readonly int $bitrixGroupId)
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function uniqueId(): string
    {
        return (string) $this->bitrixGroupId;
    }

    public function handle(BitrixSyncServiceInterface $sync): void
    {
        $sync->syncProject($this->bitrixGroupId);
    }

    /** Failed after all its tries: a dead letter on the sync status page (step 5.2). */
    protected function failureOperation(): BitrixSyncOperation
    {
        return BitrixSyncOperation::Project;
    }

    protected function failureSubject(): string
    {
        return (string) $this->bitrixGroupId;
    }
}
