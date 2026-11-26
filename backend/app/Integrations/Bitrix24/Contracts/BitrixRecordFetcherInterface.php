<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

/**
 * Reads single Bitrix24 records with the service account, in the shapes the
 * mappers expect. Used when an event names one record. Reads only.
 */
interface BitrixRecordFetcherInterface
{
    /**
     * For a deleted task Bitrix answers an empty result, not ACCESS_DENIED;
     * an error (e.g. no access) is thrown, never read as "deleted".
     *
     * @return array<string, mixed>|null Null when the task no longer exists.
     */
    public function task(int $id): ?array;

    /** @return array<string, mixed>|null Null when the group no longer exists. */
    public function group(int $id): ?array;

    /** @return list<array<string, mixed>> */
    public function members(int $groupId): array;

    /**
     * The Bitrix rows of the given users we don't have yet; the caller imports them.
     *
     * @param  list<int>  $bitrixIds
     * @return list<array<string, mixed>>
     */
    public function missingUsers(array $bitrixIds): array;
}
