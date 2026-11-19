<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Bitrix\BitrixEventDTO;
use App\Jobs\Bitrix\SyncBitrixProject;
use App\Jobs\Bitrix\SyncBitrixTask;
use App\Jobs\Bitrix\SyncBitrixTaskChat;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;
use App\Services\Contracts\BitrixEventServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * Step 3.4: the events Bitrix24 sends for the handlers bound at install
 * (BitrixInstallService::EVENTS). An event only names the record, so we
 * queue a job that fetches it.
 */
final readonly class BitrixEventService implements BitrixEventServiceInterface
{
    /** Seconds to wait before fetching: more events for the same record may follow. */
    private const DEBOUNCE_SECONDS = 3;

    public function __construct(private BitrixPortalRepositoryInterface $portals) {}

    /**
     * Bitrix signs every event with the application token it gave us at
     * install; anything else is not from our portal.
     */
    public function isFromOurPortal(mixed $applicationToken, mixed $memberId): bool
    {
        $portal = $this->portals->current();

        return $portal instanceof \App\Models\BitrixPortal
            && is_string($applicationToken)
            && hash_equals((string) $portal->application_token, $applicationToken)
            && $memberId === $portal->member_id;
    }

    public function handle(BitrixEventDTO $bitrixEvent): void
    {
        $event = $bitrixEvent->event;
        $data = $bitrixEvent->data;

        $job = match ($event) {
            'ONTASKADD', 'ONTASKUPDATE' => $this->taskJob(data_get($data, 'FIELDS_AFTER.ID')),
            'ONTASKDELETE' => $this->taskJob(data_get($data, 'FIELDS_BEFORE.ID')),
            'ONSONETGROUPADD', 'ONSONETGROUPUPDATE', 'ONSONETGROUPDELETE' => $this->projectJob(data_get($data, 'FIELDS.ID')),
            // Step 4.7: a new message in a task's chat.
            'ONTASKCOMMENTADD' => $this->chatJob(data_get($data, 'FIELDS_AFTER.TASK_ID')),
            default => null,
        };

        if ($job) {
            dispatch($job)->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
        } else {
            Log::info('Bitrix24 event ignored.', ['event' => $event]);
        }
    }

    private function taskJob(mixed $id): ?SyncBitrixTask
    {
        return (int) $id > 0 ? new SyncBitrixTask((int) $id) : null;
    }

    private function chatJob(mixed $taskId): ?SyncBitrixTaskChat
    {
        return (int) $taskId > 0 ? new SyncBitrixTaskChat((int) $taskId) : null;
    }

    private function projectJob(mixed $id): ?SyncBitrixProject
    {
        return (int) $id > 0 ? new SyncBitrixProject((int) $id) : null;
    }
}
