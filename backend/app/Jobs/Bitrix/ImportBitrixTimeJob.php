<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Services\Contracts\BitrixImportServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 4.4: brings every Bitrix task's time log up to date. Bitrix sends no
 * events for it, so this nightly pass (and opening a task) is how time
 * logged there reaches us. Run after ImportBitrixUsersJob and ImportBitrixTasksJob.
 */
final class ImportBitrixTimeJob implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = BitrixImportServiceInterface::TIME;

    public function __construct()
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(BitrixImportServiceInterface $import): void
    {
        $import->importTime();
    }
}
