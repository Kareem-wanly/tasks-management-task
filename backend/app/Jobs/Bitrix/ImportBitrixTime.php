<?php

namespace App\Jobs\Bitrix;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixTimeSync;
use App\Models\BitrixSyncCursor;
use App\Models\Task;
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
        $this->onQueue('bitrix');
    }

    public function handle(BitrixClient $bitrix, BitrixTimeSync $time): void
    {
        $cursor = BitrixSyncCursor::start(self::ENTITY);
        $query = Task::whereNotNull('bitrix_task_id');
        $total = $query->count();
        $failed = 0;

        $query->orderBy('id')->chunk(self::BATCH, function ($tasks) use ($bitrix, $time, $cursor, $total, &$failed) {
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

            $cursor->advance($tasks->count(), $total);
        });

        $cursor->complete();
        Log::info('Bitrix24 time logs imported.', ['tasks' => $cursor->processed, 'failed' => $failed]);
    }
}
