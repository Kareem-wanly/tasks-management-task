<?php

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
}
