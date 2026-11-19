<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Integrations\Bitrix24\BitrixTimeSync;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.4: brings every Bitrix task's time log up to date. Bitrix sends no
 * events for it, so this nightly pass (and opening a task) is how time
 * logged there reaches us. Run after ImportBitrixUsers and ImportBitrixTasks.
 */
class ImportBitrixTime implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = 'time';

    /** Bitrix's batch limit. */
    private const BATCH = 50;

    public function __construct()
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(BitrixGatewayInterface $bitrix, BitrixTimeSync $time, BitrixSyncCursorRepositoryInterface $cursors, TaskRepositoryInterface $taskRepository): void
    {
        $cursor = $cursors->start(self::ENTITY);
        $total = $taskRepository->countInBitrix();
        $failed = 0;

        $taskRepository->chunkInBitrix(self::BATCH, function ($tasks) use ($bitrix, $time, $cursor, $cursors, $total, &$failed): void {
            $commands = [];
            foreach ($tasks as $task) {
                $commands["task_{$task->id}"] = ['task.elapseditem.getlist', ['TASKID' => $task->bitrix_task_id]];
            }

            $batch = $bitrix->batchAsService($commands);

            foreach ($tasks as $task) {
                $key = "task_{$task->id}";
                // One task Bitrix refuses must not stop the rest, nor wipe its log.
                if (isset($batch['result_error'][$key]) || ! isset($batch['result'][$key])) {
                    $failed++;

                    continue;
                }
                $time->sync($task, (array) $batch['result'][$key]);
            }

            $cursors->advance($cursor, $tasks->count(), $total);
        });

        $cursors->complete($cursor);
        Log::info('Bitrix24 time logs imported.', ['tasks' => $cursor->processed, 'failed' => $failed]);
    }
}
