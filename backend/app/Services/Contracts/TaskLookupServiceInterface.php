<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\TaskLookup\SaveTaskLookupDTO;
use App\Models\TaskLookup;
use Illuminate\Support\Collection;

interface TaskLookupServiceInterface
{
    /**
     * @param  class-string<TaskLookup>  $model
     * @return Collection<int, TaskLookup>
     */
    public function list(string $model): Collection;

    /**
     * @param  class-string<TaskLookup>  $model
     */
    public function create(string $model, SaveTaskLookupDTO $data): TaskLookup;

    public function update(TaskLookup $lookup, SaveTaskLookupDTO $data): TaskLookup;

    public function delete(TaskLookup $lookup): void;

    /**
     * @param  class-string<TaskLookup>  $model
     * @return list<int>
     */
    public function bitrixIdsForCustomValues(string $model): array;

    /**
     * Pulls the task STATUS and PRIORITY values from Bitrix24: renames our
     * Bitrix-linked rows and adds any value we don't have yet. Our own values
     * are never touched.
     *
     * @return list<array{string, int, string, string, string}> One row per value: type, Bitrix ID, key, name, change.
     *
     * @throws \App\Exceptions\Bitrix\BitrixException When Bitrix fails, or gives no values.
     */
    public function syncFromBitrix(): array;
}
