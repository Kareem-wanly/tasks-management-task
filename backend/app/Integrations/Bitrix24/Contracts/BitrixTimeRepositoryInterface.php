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
}
