<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\TaskLookup\SaveTaskLookupDTO;
use App\Models\TaskLookup;
use Illuminate\Support\Collection;

interface TaskLookupServiceInterface
{
    /** @param  class-string<TaskLookup>  $model */
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
}
