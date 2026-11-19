<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Services\Contracts\BitrixImportServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 2.2: copies every workgroup (groups, projects, scrum, collabs) into
 * projects, with members and roles. Run after ImportBitrixUsersJob.
 * Safe to re-run: rows are matched on bitrix_group_id.
 */
final class ImportBitrixProjectsJob implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = BitrixImportServiceInterface::PROJECTS;

    public function __construct()
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(BitrixImportServiceInterface $import): void
    {
        $import->importProjects();
    }
}
