<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Bitrix\BitrixCannotSyncException;
use App\Exceptions\Bitrix\BitrixException;
use App\Services\Contracts\TaskLookupServiceInterface;
use Illuminate\Console\Command;

/**
 * Pulls the task STATUS and PRIORITY values from Bitrix24 (tasks.task.getFields):
 * renames our Bitrix-linked rows and adds any value we don't have yet.
 * Our own values (is_system = false) are never touched.
 */
final class SyncBitrixTaskLookups extends Command
{
    protected $signature = 'bitrix:sync-task-lookups';

    protected $description = 'Sync task statuses and priorities from Bitrix24';

    public function handle(TaskLookupServiceInterface $lookups): int
    {
        try {
            $rows = $lookups->syncFromBitrix();
        } catch (BitrixCannotSyncException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (BitrixException $e) {
            $this->error("Bitrix24 request failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->table(['Type', 'Bitrix ID', 'Key', 'Name', 'Change'], $rows);
        $this->info('Task statuses and priorities are in sync with Bitrix24.');

        return self::SUCCESS;
    }
}
