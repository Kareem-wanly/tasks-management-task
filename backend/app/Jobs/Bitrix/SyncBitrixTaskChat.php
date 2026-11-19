<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\TaskChatServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 4.7: brings one task's chat up to date after OnTaskCommentAdd (a new
 * message in Bitrix; the event only names the task). Unique until it
 * starts, so a burst of messages collapses into one fetch. Our own messages
 * come back too; they are matched by their Bitrix ID, so nothing doubles.
 */
class SyncBitrixTaskChat implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds before each retry, e.g. while Bitrix is unreachable. */
    public array $backoff = [10, 60];

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

    public function handle(TaskChatServiceInterface $chat, TaskRepositoryInterface $tasks): void
    {
        $task = $tasks->findByBitrixId($this->bitrixTaskId);

        if ($task instanceof \App\Models\Task && $task->isInBitrix()) {
            $chat->refreshFromBitrix($task);
        }
    }
}
