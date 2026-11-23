<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

/**
 * Step 3.6: a record gone from Bitrix24 is deleted here only when Bitrix
 * confirms it, the service account sees everything (an administrator), and
 * a run deletes at most a few at once.
 */
interface BitrixDeletionGuardInterface
{
    public function serviceAccountIsAdmin(): bool;

    /**
     * The candidates Bitrix confirms are gone, within the per-run limit.
     *
     * @param  list<int>  $bitrixIds  Our records the listing no longer returned.
     * @param  callable(int): bool  $existsInBitrix  Fetches one record from Bitrix.
     * @return list<int>
     */
    public function confirmedGone(array $bitrixIds, callable $existsInBitrix, string $entity): array;

    /**
     * @param  list<int>  $bitrixIds
     * @param  string  $via  'event' (a delete event) or 'import' (the nightly full listing).
     */
    public function recordDeleted(string $entity, array $bitrixIds, string $via): void;
}
