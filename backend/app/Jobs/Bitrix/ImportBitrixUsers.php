<?php

namespace App\Jobs\Bitrix;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixUserSync;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Step 2.1: copies every portal user (active and deactivated) into users.
 * Safe to re-run: rows are matched on bitrix_user_id.
 */
class ImportBitrixUsers implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = 'users';

    public function __construct()
    {
        $this->onQueue('bitrix');
    }

    public function handle(BitrixClient $bitrix, BitrixUserSync $users, BitrixSyncCursorRepositoryInterface $cursors): void
    {
        $cursor = $cursors->start(self::ENTITY);

        // No ACTIVE filter: ACTIVE=true would drop deactivated users, and we
        // need them to block their access here too.
        $total = $bitrix->eachPageAsService('user.get', ['sort' => 'ID', 'order' => 'ASC'], function (array $rows, int $total) use ($users, $cursor, $cursors) {
            foreach ($rows as $row) {
                $users->upsert($row);
            }
            $cursors->advance($cursor, count($rows), $total);
        });

        $cursors->complete($cursor);
        Log::info('Bitrix24 users imported.', ['imported' => $cursor->processed, 'portal_total' => $total]);
    }
}
