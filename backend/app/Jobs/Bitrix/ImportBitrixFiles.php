<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\TaskFileServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.5: brings every Bitrix task's attached files up to date. Bitrix
 * sends no events for attachments, so this nightly pass (and opening a
 * task) is how files attached there reach us. Run after ImportBitrixTasks.
 */
class ImportBitrixFiles implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = 'files';

    /** Bitrix's batch limit. */
    private const BATCH = 50;

    public function __construct()
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(TaskFileServiceInterface $files, BitrixSyncCursorRepositoryInterface $cursors, TaskRepositoryInterface $tasks): void
    {
        $cursor = $cursors->start(self::ENTITY);
        $total = $tasks->countInBitrix();
        $skipped = 0;

        $tasks->chunkInBitrix(self::BATCH, function ($chunk) use ($files, $cursor, $cursors, $total, &$skipped): void {
            $skipped += $files->refreshManyFromBitrix($chunk);
            $cursors->advance($cursor, $chunk->count(), $total);
        });

        $cursors->complete($cursor);
        Log::info('Bitrix24 task files imported.', ['tasks' => $cursor->processed, 'skipped' => $skipped]);
    }
}
