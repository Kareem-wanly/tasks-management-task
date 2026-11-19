<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Services\Contracts\BitrixImportServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 2.3: copies every portal task into tasks, open ones first, then
 * closed. Run after ImportBitrixUsersJob and ImportBitrixProjectsJob.
 * Safe to re-run: rows are matched on bitrix_task_id.
 *
 * Step 3.6: a full run also deletes tasks that are gone from Bitrix (their
 * delete event may have been lost). With $changedSince it is the periodic
 * delta instead: only tasks changed in Bitrix since then.
 */
final class ImportBitrixTasksJob implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = BitrixImportServiceInterface::TASKS;

    /** The periodic changes sync reports here, so it never overwrites the full import's row. */
    public const CHANGES_ENTITY = BitrixImportServiceInterface::TASKS_CHANGES;

    public function __construct(public readonly ?CarbonImmutable $changedSince = null)
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(BitrixImportServiceInterface $import): void
    {
        $import->importTasks($this->changedSince);
    }

    protected function cursorEntity(): string
    {
        return $this->changedSince instanceof CarbonImmutable ? self::CHANGES_ENTITY : self::ENTITY;
    }
}
