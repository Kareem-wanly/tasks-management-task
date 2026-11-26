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
 * Step 4.7: brings one task's chat up to date after OnTaskCommentAdd (a new
 * message in Bitrix; the event only names the task). Unique until it
 * starts, so a burst of messages collapses into one fetch. Our own messages
 * come back too; they are matched by their Bitrix ID, so nothing doubles.
 */
final class SyncBitrixTaskChatJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Seconds before each retry, e.g. while Bitrix is unreachable.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60];

    /** One page of messages; well under $uniqueFor. */
    public int $timeout = 90;

    /** Safety net: the lock never outlives a stuck job by much. */
    public int $uniqueFor = 120;

    public function __construct(public readonly int $bitrixTaskId)
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function uniqueId(): string
    {
        return 'chat:'.$this->bitrixTaskId;
    }

    public function handle(BitrixSyncServiceInterface $sync): void
    {
        $sync->syncTaskChat($this->bitrixTaskId);
    }

    /** The chat is fetched again when the task is opened, and by the nightly import. */
    public function failed(?Throwable $exception): void
    {
        Log::warning('Bitrix24 task chat sync failed.', ['bitrix_task_id' => $this->bitrixTaskId, 'error' => $exception?->getMessage()]);
    }
}
