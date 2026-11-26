<?php

declare(strict_types=1);

namespace App\Jobs\Bitrix;

use App\Enums\QueueName;
use App\Services\Contracts\BitrixImportServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Step 2.1: copies every portal user (active and deactivated) into users.
 * Safe to re-run: rows are matched on bitrix_user_id. Existing local
 * accounts are linked by email only when an administrator asks for it
 * (bitrix:import --link-by-email), never on the scheduled runs.
 */
final class ImportBitrixUsers implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = BitrixImportServiceInterface::USERS;

    public function __construct(public readonly bool $linkByEmail = false)
    {
        $this->onQueue(QueueName::Bitrix);
    }

    public function handle(BitrixImportServiceInterface $import): void
    {
        $import->importUsers($this->linkByEmail);
    }
}
