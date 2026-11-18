<?php

namespace App\Integrations\Bitrix24;

use Illuminate\Support\Facades\Log;

/**
 * Decides which of our Bitrix-linked records may be deleted because they are
 * gone from Bitrix. "Not in the listing" is not proof: the service account
 * may simply not see the record, or Bitrix may have answered badly. So:
 *
 * - nothing is deleted unless the service account is a portal administrator
 *   right now (only an admin sees every task and group);
 * - every record is fetched once more and deleted only if Bitrix confirms
 *   it is gone (tasks.task.get answers an empty result for a deleted task,
 *   checked on the real portal);
 * - a run that would delete more than MAX_PER_RUN records deletes none and
 *   logs a warning, since a mass deletion is far more likely a fault.
 */
class BitrixDeletionGuard
{
    public const MAX_PER_RUN = 20;

    public function __construct(private readonly BitrixClient $bitrix) {}

    public function serviceAccountIsAdmin(): bool
    {
        try {
            $isAdmin = (bool) ($this->bitrix->callAsService('user.admin')['result'] ?? false);
        } catch (BitrixException) {
            $isAdmin = false;
        }

        if (! $isAdmin) {
            Log::warning('Bitrix24 deletions skipped: the service account is not a portal administrator.');
        }

        return $isAdmin;
    }

    /**
     * The candidates Bitrix confirms are gone, within the per-run limit.
     *
     * @param  list<int>  $bitrixIds  Our records the listing no longer returned.
     * @param  callable(int): bool  $existsInBitrix  Fetches one record from Bitrix.
     * @return list<int>
     */
    public function confirmedGone(array $bitrixIds, callable $existsInBitrix, string $entity): array
    {
        if ($bitrixIds === [] || ! $this->serviceAccountIsAdmin()) {
            return [];
        }

        if (count($bitrixIds) > self::MAX_PER_RUN) {
            Log::warning("Bitrix24 deletions skipped: too many {$entity} missing from the listing at once.", [
                'missing' => count($bitrixIds),
                'limit' => self::MAX_PER_RUN,
            ]);

            return [];
        }

        return array_values(array_filter($bitrixIds, function (int $bitrixId) use ($existsInBitrix, $entity) {
            try {
                $gone = ! $existsInBitrix($bitrixId);
            } catch (BitrixException) {
                // Bitrix didn't answer clearly: keep the record.
                $gone = false;
            }

            if (! $gone) {
                Log::info("Bitrix24 {$entity} kept: missing from the listing but not confirmed gone.", ['bitrix_id' => $bitrixId]);
            }

            return $gone;
        }));
    }

    /**
     * Notes what was deleted here because it is gone from Bitrix (IDs only).
     *
     * @param  list<int>  $bitrixIds
     * @param  string  $via  'event' (a delete event) or 'import' (the nightly full listing).
     */
    public function recordDeleted(string $entity, array $bitrixIds, string $via): void
    {
        if ($bitrixIds !== []) {
            Log::info("Bitrix24 {$entity} deleted here: gone from Bitrix.", ['bitrix_ids' => array_values($bitrixIds), 'via' => $via]);
        }
    }
}
