<?php

namespace App\Console\Commands;

use App\Jobs\Bitrix\ImportBitrixProjects;
use App\Jobs\Bitrix\ImportBitrixTasks;
use App\Models\BitrixSyncCursor;
use Illuminate\Console\Command;

/**
 * Step 3.6: the safety net behind the events. Runs every few minutes and
 * brings in whatever changed in Bitrix since the last sync, so an event
 * lost while we were down (server, worker or tunnel) loses no data.
 */
class SyncBitrixChanges extends Command
{
    protected $signature = 'bitrix:sync-changes';

    protected $description = 'Bring in the projects and tasks changed in Bitrix24 since the last sync';

    /** Overlap with the previous run, against clock drift between us and Bitrix. */
    private const OVERLAP_SECONDS = 60;

    public function handle(): int
    {
        $since = BitrixSyncCursor::for(ImportBitrixTasks::ENTITY)->last_synced_at;

        // Never synced: nothing to measure changes against, so import everything.
        if ($since === null) {
            return $this->call('bitrix:import', ['--sync' => true]);
        }

        // Few groups, and no reliable change filter for them: always all of them.
        ImportBitrixProjects::dispatchSync();
        ImportBitrixTasks::dispatchSync($since->toImmutable()->subSeconds(self::OVERLAP_SECONDS));

        $this->info("Bitrix24 changes synced since {$since->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
