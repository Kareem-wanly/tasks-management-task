<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Integrations\Bitrix24\BitrixChecklistSync;
use App\Integrations\Bitrix24\BitrixClient;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.3: brings every Bitrix task's checklists up to date. Bitrix sends
 * no events for checklists, so this nightly pass (and opening a task)
 * is how changes made there reach us. Run after ImportBitrixTasks.
 */
class ImportBitrixChecklists implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = 'checklists';

    /** Bitrix's batch limit. */
    private const BATCH = 50;

    public function __construct()
    {
        $this->onQueue('bitrix');
    }

    public function handle(BitrixClient $bitrix, BitrixChecklistSync $checklists, BitrixSyncCursorRepositoryInterface $cursors, TaskRepositoryInterface $taskRepository): void
    {
        $cursor = $cursors->start(self::ENTITY);
        $total = $taskRepository->countInBitrix();
        $failed = 0;

        $taskRepository->chunkInBitrix(self::BATCH, function ($tasks) use ($bitrix, $checklists, $cursor, $cursors, $total, &$failed): void {
            $commands = [];
            foreach ($tasks as $task) {
                $commands["task_{$task->id}"] = ['task.checklistitem.getlist', ['TASKID' => $task->bitrix_task_id, 'ORDER' => ['SORT_INDEX' => 'asc']]];
            }

            $batch = $bitrix->batchAsService($commands);

            foreach ($tasks as $task) {
                $key = "task_{$task->id}";
                // One task Bitrix refuses (e.g. deleted meanwhile) must not stop the rest, nor wipe its list.
                if (isset($batch['result_error'][$key]) || ! isset($batch['result'][$key])) {
                    $failed++;

                    continue;
                }
                $checklists->sync($task, (array) $batch['result'][$key]);
            }

            $cursors->advance($cursor, $tasks->count(), $total);
        });

        $cursors->complete($cursor);
        Log::info('Bitrix24 checklists imported.', ['tasks' => $cursor->processed, 'failed' => $failed]);
    }
}
