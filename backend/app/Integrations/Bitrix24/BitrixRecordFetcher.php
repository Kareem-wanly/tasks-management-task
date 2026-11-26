<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Mappers\BitrixProjectMapper;
use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Fetches single Bitrix24 records with the service account, in the shapes
 * the import mappers expect. Used when an event names one record.
 */
final readonly class BitrixRecordFetcher
{
    public function __construct(
        private BitrixGatewayInterface $bitrix,
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Checked on the real portal (2026-11-17): for a deleted task Bitrix answers
     * an empty result, not ACCESS_DENIED. An error (e.g. no access) is thrown
     * as a BitrixException, never read as "deleted".
     *
     * @return array<string, mixed>|null Null when the task no longer exists.
     */
    public function task(int $id): ?array
    {
        $task = BitrixPayload::of($this->bitrix->callAsService('tasks.task.get', ['taskId' => $id, 'select' => BitrixTaskMapper::SELECT]), 'tasks.task.get')
            ->payload('result.task');

        return $task->isEmpty() ? null : $task->toRow();
    }

    /**
     * @return array<string, mixed>|null Null when the group no longer exists.
     */
    public function group(int $id): ?array
    {
        $group = BitrixPayload::of($this->bitrix->callAsService('socialnetwork.api.workgroup.list', [
            'filter' => ['ID' => $id],
            'select' => BitrixProjectMapper::SELECT,
            'params' => ['IS_ADMIN' => 'Y'],
        ]), 'socialnetwork.api.workgroup.list')->payload('result.workgroups.0');

        return $group->isEmpty() ? null : $group->toRow();
    }

    /** @return list<array<string, mixed>> */
    public function members(int $groupId): array
    {
        return BitrixPayload::of($this->bitrix->callAsService('sonet_group.user.get', ['ID' => $groupId]), 'sonet_group.user.get')->rows('result');
    }

    /**
     * The Bitrix rows of the given users we don't have yet (e.g. someone who
     * joined after the last import); the caller imports them, so records
     * pointing at them can be saved.
     *
     * @param  list<int>  $bitrixIds
     * @return list<array<string, mixed>>
     */
    public function missingUsers(array $bitrixIds): array
    {
        $bitrixIds = array_values(array_filter(array_unique($bitrixIds)));
        $known = array_keys($this->userRepository->idsByBitrixIds($bitrixIds));

        $rows = [];
        foreach (array_diff($bitrixIds, $known) as $bitrixId) {
            $rows = [...$rows, ...BitrixPayload::of($this->bitrix->callAsService('user.get', ['FILTER' => ['ID' => $bitrixId]]), 'user.get')->rows('result')];
        }

        return $rows;
    }
}
