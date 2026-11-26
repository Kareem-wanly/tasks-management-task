<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Exceptions\Bitrix\BitrixException;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\TaskChatServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.7: brings every Bitrix task's whole chat up to date. Edits and
 * deletes in Bitrix send no events, so this nightly pass (and opening a
 * task, for its newest messages) is how they reach us.
 */
class ImportBitrixChat implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = 'chat';

    private const CHUNK = 50;

    public function __construct()
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(TaskChatServiceInterface $chat, BitrixSyncCursorRepositoryInterface $cursors, TaskRepositoryInterface $tasks): void
    {
        $cursor = $cursors->start(self::ENTITY);
        $total = $tasks->countInBitrix();
        $failed = 0;

        $tasks->chunkInBitrix(self::CHUNK, function ($chunk) use ($chat, $cursor, $cursors, $total, &$failed): void {
            foreach ($chunk as $task) {
                try {
                    $chat->refreshFromBitrix($task, complete: true);
                } catch (BitrixException $e) {
                    // One chat Bitrix refuses must not stop the rest, nor wipe its messages.
                    if ($e->errorCode === BitrixException::UNREACHABLE) {
                        throw $e;
                    }
                    $failed++;
                }
            }

            $cursors->advance($cursor, $chunk->count(), $total);
        });

        $cursors->complete($cursor);
        Log::info('Bitrix24 task chats imported.', ['tasks' => $cursor->processed, 'failed' => $failed]);
    }
}
