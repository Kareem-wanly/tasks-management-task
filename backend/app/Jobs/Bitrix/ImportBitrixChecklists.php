<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Services\Contracts\BitrixImportServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 4.3: brings every Bitrix task's checklists up to date. Bitrix sends
 * no events for checklists, so this nightly pass (and opening a task)
 * is how changes made there reach us. Run after ImportBitrixTasks.
 */
final class ImportBitrixChecklists implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = BitrixImportServiceInterface::CHECKLISTS;

    public function __construct()
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(BitrixImportServiceInterface $import): void
    {
        $import->importChecklists();
    }
}
