<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Models\User;

/**
 * A task's time log in Bitrix24 (task.elapseditem.*), through the gateway.
 * Reads and writes only; TaskTimeService decides when and whether.
 */
interface BitrixTimeRepositoryInterface
{
    /**
     * The task's time entries, read with the service account.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $bitrixTaskId): array;

    public function add(User $actor, int $bitrixTaskId, int $seconds, ?string $comment): void;

    public function update(User $actor, int $bitrixTaskId, int $bitrixItemId, int $seconds, ?string $comment): void;

    public function delete(User $actor, int $bitrixTaskId, int $bitrixItemId): void;

    /**
     * The same for many tasks, in one batched request. A task Bitrix refuses
     * (e.g. deleted meanwhile) comes back as null, so its copy is kept.
     *
     * @param  array<int, int>  $bitrixTaskIds  Bitrix task ID by our task ID.
     * @return array<int, list<array<string, mixed>>|null> By our task ID.
     */
    public function listMany(array $bitrixTaskIds): array;
}
