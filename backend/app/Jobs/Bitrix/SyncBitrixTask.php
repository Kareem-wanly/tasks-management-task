<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Services\Contracts\BitrixSyncServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Step 3.4: brings one Bitrix24 task up to date after a task event (add,
 * update or delete: the event only names the task). Unique until it
 * starts, so the events Bitrix sends for one change collapse into one
 * fetch (debounce).
 */
final class SyncBitrixTask implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds before each retry, e.g. while Bitrix is unreachable. */
    public array $backoff = [10, 60];

    /** One task's fetch and save; well under $uniqueFor. */
    public int $timeout = 90;

    /** Safety net: the lock never outlives a stuck job by much. */
    public int $uniqueFor = 120;

    public function __construct(public readonly int $bitrixTaskId)
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function uniqueId(): string
    {
        return (string) $this->bitrixTaskId;
    }

    public function handle(BitrixSyncServiceInterface $sync): void
    {
        $sync->syncTask($this->bitrixTaskId);
    }

    /** The next event, or the nightly import, brings the task up to date. */
    public function failed(?Throwable $exception): void
    {
        Log::warning('Bitrix24 task sync failed.', ['bitrix_task_id' => $this->bitrixTaskId, 'error' => $exception?->getMessage()]);
    }
}
