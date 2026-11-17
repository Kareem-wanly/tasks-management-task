<?php

namespace App\Integrations\Bitrix24;

use App\Integrations\Bitrix24\Mappers\BitrixProjectMapper;
use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Models\User;

/**
 * Fetches single Bitrix24 records with the service account, in the shapes
 * the import mappers expect. Used when an event names one record.
 */
class BitrixRecordFetcher
{
    public function __construct(
        private readonly BitrixClient $bitrix,
        private readonly BitrixUserSync $users,
    ) {}

    /**
     * Checked on the real portal (2026-11-17): for a deleted task Bitrix answers
     * an empty result, not ACCESS_DENIED. An error (e.g. no access) is thrown
     * as a BitrixException, never read as "deleted".
     *
     * @return array|null Null when the task no longer exists.
     */
    public function task(int $id): ?array
    {
        $task = $this->bitrix->callAsService('tasks.task.get', ['taskId' => $id, 'select' => BitrixTaskMapper::SELECT])['result']['task'] ?? null;

        return is_array($task) && $task !== [] ? $task : null;
    }

    /** @return array|null Null when the group no longer exists. */
    public function group(int $id): ?array
    {
        return $this->bitrix->callAsService('socialnetwork.api.workgroup.list', [
            'filter' => ['ID' => $id],
            'select' => BitrixProjectMapper::SELECT,
            'params' => ['IS_ADMIN' => 'Y'],
        ])['result']['workgroups'][0] ?? null;
    }

    public function members(int $groupId): array
    {
        return $this->bitrix->callAsService('sonet_group.user.get', ['ID' => $groupId])['result'] ?? [];
    }

    /**
     * Imports the given Bitrix users we don't have yet (e.g. someone who
     * joined after the last import), so records pointing at them can be saved.
     *
     * @param  list<int>  $bitrixIds
     */
    public function importMissingUsers(array $bitrixIds): void
    {
        $bitrixIds = array_values(array_filter(array_unique($bitrixIds)));
        $known = User::whereIn('bitrix_user_id', $bitrixIds)->pluck('bitrix_user_id')->all();

        foreach (array_diff($bitrixIds, $known) as $bitrixId) {
            foreach ($this->bitrix->callAsService('user.get', ['FILTER' => ['ID' => $bitrixId]])['result'] ?? [] as $row) {
                $this->users->upsert($row);
            }
        }
    }
}
