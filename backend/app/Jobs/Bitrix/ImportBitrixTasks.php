<?php

namespace App\Jobs\Bitrix;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixTaskSync;
use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Models\BitrixSyncCursor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Step 2.3: copies every portal task into tasks, open ones first, then
 * closed. Run after ImportBitrixUsers and ImportBitrixProjects.
 * Safe to re-run: rows are matched on bitrix_task_id.
 */
class ImportBitrixTasks implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = 'tasks';

    /** Bitrix STATUS of a closed (completed) task. */
    private const STATUS_COMPLETED = 5;

    public function __construct()
    {
        $this->onQueue('bitrix');
    }

    public function handle(BitrixClient $bitrix, BitrixTaskSync $tasks): void
    {
        $cursor = BitrixSyncCursor::start(self::ENTITY);
        $skipped = 0;
        // Totals of the passes already finished; the closed pass's own total
        // is only known once it starts.
        $earlierTotal = 0;

        foreach ([['!STATUS' => self::STATUS_COMPLETED], ['STATUS' => self::STATUS_COMPLETED]] as $filter) {
            $earlierTotal += $bitrix->eachPageAsService('tasks.task.list', [
                'select' => BitrixTaskMapper::SELECT,
                'filter' => $filter,
                'order' => ['ID' => 'ASC'],
            ], function (array $page, int $total) use ($tasks, $cursor, $earlierTotal, &$skipped) {
                $rows = $page['tasks'] ?? [];

                foreach ($rows as $row) {
                    if (! $tasks->upsert($row)) {
                        $skipped++;
                    }
                }

                $cursor->advance(count($rows), $earlierTotal + $total);
            });
        }

        $cursor->complete();
        Log::info('Bitrix24 tasks imported.', ['processed' => $cursor->processed, 'skipped' => $skipped, 'portal_total' => $earlierTotal]);
    }
}
