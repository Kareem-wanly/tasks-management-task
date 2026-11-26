<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Services\Contracts\BitrixImportServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 4.5: brings every Bitrix task's attached files up to date. Bitrix
 * sends no events for attachments, so this nightly pass (and opening a
 * task) is how files attached there reach us. Run after ImportBitrixTasksJob.
 */
final class ImportBitrixFilesJob implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = BitrixImportServiceInterface::FILES;

    public function __construct()
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(BitrixImportServiceInterface $import): void
    {
        $import->importFiles();
    }
}
