<?php

namespace App\Jobs\Bitrix;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixUserSync;
use App\Models\BitrixSyncCursor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Step 2.1: copies every portal user (active and deactivated) into users.
 * Safe to re-run: rows are matched on bitrix_user_id. Existing local
 * accounts are linked by email only when an administrator asks for it
 * (bitrix:import --link-by-email), never on the scheduled runs.
 */
class ImportBitrixUsers implements ShouldQueue
{
    use Queueable, TracksSyncProgress;

    public const ENTITY = 'users';

    public function __construct(public readonly bool $linkByEmail = false)
    {
        $this->onQueue('bitrix');
    }

    public function handle(BitrixClient $bitrix, BitrixUserSync $users): void
    {
        $cursor = BitrixSyncCursor::start(self::ENTITY);
        $notLinked = 0;

        // No ACTIVE filter: ACTIVE=true would drop deactivated users, and we
        // need them to block their access here too.
        $total = $bitrix->eachPageAsService('user.get', ['sort' => 'ID', 'order' => 'ASC'], function (array $rows, int $total) use ($users, $cursor, &$notLinked) {
            foreach ($rows as $row) {
                if ($users->upsert($row, $this->linkByEmail) === null) {
                    $notLinked++;
                }
            }
            $cursor->advance(count($rows), $total);
        });

        $cursor->complete();
        Log::info('Bitrix24 users imported.', ['imported' => $cursor->processed, 'not_linked' => $notLinked, 'portal_total' => $total]);
    }
}
