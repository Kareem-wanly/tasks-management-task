<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Models\User;

/**
 * A task's results in Bitrix24 (REST 3.0 tasks.task.result.*), through the
 * gateway. Reads and writes only; TaskResultService decides when and whether.
 */
interface BitrixResultRepositoryInterface
{
    /**
     * The task's results, newest first, read with the service account.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $bitrixTaskId): array;

    public function add(User $actor, int $bitrixTaskId, string $text): void;

    /** Bitrix lets only the result's author change it. */
    public function update(User $actor, int $bitrixResultId, string $text): void;

    public function delete(User $actor, int $bitrixResultId): void;
}
