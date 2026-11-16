<?php

namespace App\Jobs\Bitrix;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixRecordFetcher;
use App\Integrations\Bitrix24\BitrixTaskSync;
use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Models\BitrixSyncCursor;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Step 2.3: copies every portal task into tasks, open ones first, then
 * closed. Run after ImportBitrixUsers and ImportBitrixProjects.
 * Safe to re-run: rows are matched on bitrix_task_id.
 *
 * Step 3.6: a full run also deletes tasks that are gone from Bitrix (their
 * delete event may have been lost). With $changedSince it is the periodic
 * delta instead: only tasks changed in Bitrix since then.
 */
class ImportBitrixTasks implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = 'tasks';

    /** Bitrix STATUS of a closed (completed) task. */
    private const STATUS_COMPLETED = 5;

    public function __construct(public readonly ?CarbonImmutable $changedSince = null)
    {
        $this->onQueue('bitrix');
    }

    public function handle(BitrixClient $bitrix, BitrixTaskSync $tasks, BitrixTaskMapper $mapper, BitrixRecordFetcher $fetch): void
    {
        $cursor = BitrixSyncCursor::start(self::ENTITY);
        $skipped = 0;
        $seen = [];
        // Totals of the passes already finished; the closed pass's own total
        // is only known once it starts.
        $earlierTotal = 0;

        foreach ($this->filters() as $filter) {
            $earlierTotal += $bitrix->eachPageAsService('tasks.task.list', [
                'select' => BitrixTaskMapper::SELECT,
                'filter' => $filter,
                'order' => ['ID' => 'ASC'],
            ], function (array $page, int $total) use ($tasks, $mapper, $fetch, $cursor, $earlierTotal, &$skipped, &$seen) {
                $rows = $page['tasks'] ?? [];

                // A full import runs after the users; a delta may meet someone new.
                if ($this->changedSince) {
                    $people = [];
                    foreach ($rows as $row) {
                        array_push($people, $mapper->creatorBitrixId($row), $mapper->responsibleBitrixId($row));
                    }
                    $fetch->importMissingUsers($people);
                }

                foreach ($rows as $row) {
                    $seen[] = (int) $row['id'];
                    if (! $tasks->upsert($row)) {
                        $skipped++;
                    }
                }

                $cursor->advance(count($rows), $earlierTotal + $total);
            });
        }

        $deleted = $this->changedSince ? 0 : $this->deleteMissing($seen);

        $cursor->complete();
        Log::info($this->changedSince ? 'Bitrix24 task changes synced.' : 'Bitrix24 tasks imported.', [
            'processed' => $cursor->processed, 'skipped' => $skipped, 'deleted' => $deleted, 'portal_total' => $earlierTotal,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function filters(): array
    {
        if ($this->changedSince) {
            return [['>CHANGED_DATE' => $this->changedSince->toIso8601String()]];
        }

        return [['!STATUS' => self::STATUS_COMPLETED], ['STATUS' => self::STATUS_COMPLETED]];
    }

    /**
     * Deletes our copies of Bitrix tasks the full listing no longer returns.
     *
     * @param  list<int>  $seen
     */
    private function deleteMissing(array $seen): int
    {
        // An empty listing is more likely a Bitrix hiccup than an empty portal.
        if ($seen === []) {
            return 0;
        }

        $gone = Task::whereNotNull('bitrix_task_id')->whereNotIn('bitrix_task_id', $seen)->get();
        $gone->each->delete();

        return $gone->count();
    }
}
