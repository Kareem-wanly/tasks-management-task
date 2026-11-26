<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Contracts\BitrixImportServiceInterface;
use Illuminate\Console\Command;

/**
 * Step 3.6: the safety net behind the events. Runs every few minutes and
 * brings in whatever changed in Bitrix since the last sync, so an event
 * lost while we were down (server, worker or tunnel) loses no data.
 */
final class SyncBitrixChangesCommand extends Command
{
    protected $signature = 'bitrix:sync-changes';

    protected $description = 'Bring in the projects and tasks changed in Bitrix24 since the last sync';

    public function handle(BitrixImportServiceInterface $import): int
    {
        $since = $import->lastTaskSync();

        // Never synced: nothing to measure changes against, so import everything.
        if (! $since instanceof \Carbon\CarbonImmutable) {
            return $this->call('bitrix:import', ['--sync' => true]);
        }

        $import->syncChanges($since);
        $this->info("Bitrix24 changes synced since {$since->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
