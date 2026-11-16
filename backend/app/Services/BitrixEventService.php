<?php

namespace App\Services;

use App\Jobs\Bitrix\SyncBitrixProject;
use App\Jobs\Bitrix\SyncBitrixTask;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Step 3.4: the events Bitrix24 sends for the handlers bound at install
 * (BitrixInstallService::EVENTS). An event only names the record, so we
 * queue a job that fetches it.
 */
class BitrixEventService
{
    /** Seconds to wait before fetching: more events for the same record may follow. */
    private const DEBOUNCE_SECONDS = 3;

    public function __construct(private readonly BitrixPortalRepositoryInterface $portals) {}

    /**
     * Bitrix signs every event with the application token it gave us at
     * install; anything else is not from our portal.
     */
    public function isFromOurPortal(mixed $applicationToken, mixed $memberId): bool
    {
        $portal = $this->portals->current();

        return $portal !== null
            && is_string($applicationToken)
            && hash_equals((string) $portal->application_token, $applicationToken)
            && $memberId === $portal->member_id;
    }

    /** @param  array<string, mixed>  $data  The event's data. */
    public function handle(string $event, array $data): void
    {
        $event = strtoupper($event);

        $job = match ($event) {
            'ONTASKADD', 'ONTASKUPDATE' => $this->taskJob(data_get($data, 'FIELDS_AFTER.ID')),
            'ONTASKDELETE' => $this->taskJob(data_get($data, 'FIELDS_BEFORE.ID')),
            'ONSONETGROUPADD', 'ONSONETGROUPUPDATE', 'ONSONETGROUPDELETE' => $this->projectJob(data_get($data, 'FIELDS.ID')),
            // Comments arrive with the task chat (step 4.7).
            default => null,
        };

        if ($job) {
            dispatch($job)->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
        } elseif ($event !== 'ONTASKCOMMENTADD') {
            Log::info('Bitrix24 event ignored.', ['event' => $event]);
        }
    }

    private function taskJob(mixed $id): ?SyncBitrixTask
    {
        return (int) $id > 0 ? new SyncBitrixTask((int) $id) : null;
    }

    private function projectJob(mixed $id): ?SyncBitrixProject
    {
        return (int) $id > 0 ? new SyncBitrixProject((int) $id) : null;
    }
}
