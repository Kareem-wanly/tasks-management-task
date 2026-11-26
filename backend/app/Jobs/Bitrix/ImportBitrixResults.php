<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Services\Contracts\BitrixImportServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 4.6: brings every Bitrix task's results up to date. Bitrix sends no
 * events for results, so this nightly pass (and opening a task) is how
 * results added there reach us. REST 3.0 has no batch: one call per task.
 */
final class ImportBitrixResults implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = BitrixImportServiceInterface::RESULTS;

    public function __construct()
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(BitrixImportServiceInterface $import): void
    {
        $import->importResults();
    }
}
