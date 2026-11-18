<?php

namespace App\Jobs\Bitrix;

use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixResultPusher;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.6: brings every Bitrix task's results up to date. Bitrix sends no
 * events for results, so this nightly pass (and opening a task) is how
 * results added there reach us. REST 3.0 has no batch: one call per task.
 */
class ImportBitrixResults implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = 'results';

    private const CHUNK = 50;

    public function __construct()
    {
        $this->onQueue('bitrix');
    }

    public function handle(BitrixResultPusher $results, BitrixSyncCursorRepositoryInterface $cursors, TaskRepositoryInterface $tasks): void
    {
        $cursor = $cursors->start(self::ENTITY);
        $total = $tasks->countInBitrix();
        $failed = 0;

        $tasks->chunkInBitrix(self::CHUNK, function ($chunk) use ($results, $cursor, $cursors, $total, &$failed) {
            foreach ($chunk as $task) {
                try {
                    $results->refresh($task);
                } catch (BitrixException $e) {
                    // One task Bitrix refuses must not stop the rest, nor wipe its list.
                    if ($e->errorCode === BitrixException::UNREACHABLE) {
                        throw $e;
                    }
                    $failed++;
                }
            }

            $cursors->advance($cursor, $chunk->count(), $total);
        });

        $cursors->complete($cursor);
        Log::info('Bitrix24 task results imported.', ['tasks' => $cursor->processed, 'failed' => $failed]);
    }
}
