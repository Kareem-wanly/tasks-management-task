<?php

namespace App\Repositories\Eloquent;

use App\Models\TaskLookup;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use Illuminate\Support\Collection;

class TaskLookupRepository implements TaskLookupRepositoryInterface
{
    public function ordered(string $model): Collection
    {
        return $model::ordered()->get();
    }

    public function create(string $model, array $attributes): TaskLookup
    {
        return $model::create($attributes + ['sort' => $model::max('sort') + 1])->fresh();
    }

    public function update(TaskLookup $lookup, array $attributes): TaskLookup
    {
        $lookup->update($attributes);

        return $lookup->fresh();
    }

    public function delete(TaskLookup $lookup): void
    {
        $lookup->delete();
    }

    public function systemBitrixIds(string $model): array
    {
        return $model::where('is_system', true)->whereNotNull('bitrix_id')->pluck('bitrix_id')->map(fn ($id) => (int) $id)->all();
    }
}
