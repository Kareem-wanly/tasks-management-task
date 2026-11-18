<?php

namespace App\Http\Controllers;

use App\Jobs\Bitrix\SyncBitrixProject;
use App\Jobs\Bitrix\SyncBitrixTask;
use App\Models\BitrixPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Step 3.4: Bitrix24 calls this for the events bound at install
 * (BitrixInstallController::EVENTS). An event only names the record, so we
 * queue a job that fetches it, and answer at once.
 */
class BitrixEventController extends Controller
{
    /** Seconds to wait before fetching: more events for the same record may follow. */
    private const DEBOUNCE_SECONDS = 3;

    public function handle(Request $request): JsonResponse
    {
        if (! $this->isFromOurPortal($request)) {
            return response()->json(['message' => 'Unknown application token.'], 401);
        }

        $event = strtoupper((string) $request->input('event'));

        $job = match ($event) {
            'ONTASKADD', 'ONTASKUPDATE' => $this->taskJob($request->input('data.FIELDS_AFTER.ID')),
            'ONTASKDELETE' => $this->taskJob($request->input('data.FIELDS_BEFORE.ID')),
            'ONSONETGROUPADD', 'ONSONETGROUPUPDATE', 'ONSONETGROUPDELETE' => $this->projectJob($request->input('data.FIELDS.ID')),
            // Comments arrive with the task chat (step 4.7).
            default => null,
        };

        if ($job) {
            dispatch($job)->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
        } elseif ($event !== 'ONTASKCOMMENTADD') {
            Log::info('Bitrix24 event ignored.', ['event' => $event]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Bitrix signs every event with the application token it gave us at
     * install; anything else is not from our portal.
     */
    private function isFromOurPortal(Request $request): bool
    {
        $portal = BitrixPortal::current();
        $token = $request->input('auth.application_token');

        return $portal !== null
            && is_string($token)
            && hash_equals((string) $portal->application_token, $token)
            && $request->input('auth.member_id') === $portal->member_id;
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
