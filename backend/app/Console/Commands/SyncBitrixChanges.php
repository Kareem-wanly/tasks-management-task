<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Bitrix\ImportBitrixProjects;
use App\Jobs\Bitrix\ImportBitrixTasks;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Step 3.6: the safety net behind the events. Runs every few minutes and
 * brings in whatever changed in Bitrix since the last sync, so an event
 * lost while we were down (server, worker or tunnel) loses no data.
 */
class SyncBitrixChanges extends Command
{
    /** Overlap with the previous run, against clock drift between us and Bitrix. */
    private const OVERLAP_SECONDS = 60;

    protected $signature = 'bitrix:sync-changes';

    protected $description = 'Bring in the projects and tasks changed in Bitrix24 since the last sync';

    public function handle(BitrixSyncCursorRepositoryInterface $cursors): int
    {
        // The full import and the changes sync each keep their own row: changes are
        // asked for since whichever of them last finished.
        $since = collect([ImportBitrixTasks::ENTITY, ImportBitrixTasks::CHANGES_ENTITY])
            ->map(fn (string $entity) => $cursors->forEntity($entity)->last_synced_at)
            ->filter()
            ->max();

        // Never synced: nothing to measure changes against, so import everything.
        if ($since === null) {
            return $this->call('bitrix:import', ['--sync' => true]);
        }

        // Few groups, and no reliable change filter for them: always all of them.
        dispatch_sync(new ImportBitrixProjects);
        dispatch_sync(new ImportBitrixTasks($since->toImmutable()->subSeconds(self::OVERLAP_SECONDS)));

        $this->info("Bitrix24 changes synced since {$since->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
