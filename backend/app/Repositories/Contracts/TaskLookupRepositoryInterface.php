<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TaskLookup;
use Illuminate\Support\Collection;

/**
 * Task statuses and priorities: the same table shape, chosen by model class.
 */
interface TaskLookupRepositoryInterface
{
    /** @param  class-string<TaskLookup>  $model */
    public function ordered(string $model): Collection;

    /**
     * Added after the others.
     *
     * @param  class-string<TaskLookup>  $model
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $model, array $attributes): TaskLookup;

    /** @param  array<string, mixed>  $attributes */
    public function update(TaskLookup $lookup, array $attributes): TaskLookup;

    public function delete(TaskLookup $lookup): void;

    /**
     * Bitrix values that our own values may be sent as.
     *
     * @param  class-string<TaskLookup>  $model
     * @return list<int>
     */
    public function systemBitrixIds(string $model): array;

    /**
     * Our key for a Bitrix value (a Bitrix row beats our own value that sends the same number).
     *
     * @param  class-string<TaskLookup>  $model
     */
    public function keyForBitrix(string $model, int $bitrixId): ?string;

    /**
     * The Bitrix value a key of ours is sent as.
     *
     * @param  class-string<TaskLookup>  $model
     */
    public function bitrixIdForKey(string $model, ?string $key): ?int;

    /** Whether any task (deleted ones too) uses the value. */
    public function isUsed(TaskLookup $lookup): bool;

    /**
     * The value that mirrors a Bitrix one, if we have it.
     *
     * @param  class-string<TaskLookup>  $model
     */
    public function findSystem(string $model, int $bitrixId): ?TaskLookup;

    /** @param  class-string<TaskLookup>  $model */
    public function nextSort(string $model): int;

    public function save(TaskLookup $lookup): void;
}
