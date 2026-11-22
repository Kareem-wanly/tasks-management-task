<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Models\TaskLookup;
use App\Repositories\Concerns\ReadsColumns;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

final class EloquentTaskLookupRepository implements TaskLookupRepositoryInterface
{
    use ReadsColumns;

    /** @return Collection<int, TaskLookup> */
    public function ordered(string $model): Collection
    {
        return $model::ordered()->get();
    }

    public function create(string $model, array $attributes): TaskLookup
    {
        $lookup = $model::create($attributes + ['sort' => $this->nextSort($model)]);

        return $lookup->fresh() ?? throw (new ModelNotFoundException)->setModel($model, [$lookup->id]);
    }

    public function update(TaskLookup $lookup, array $attributes): TaskLookup
    {
        $lookup->update($attributes);

        return $lookup->fresh() ?? throw (new ModelNotFoundException)->setModel($lookup::class, [$lookup->id]);
    }

    public function delete(TaskLookup $lookup): void
    {
        $lookup->delete();
    }

    public function systemBitrixIds(string $model): array
    {
        return self::toIntList($model::where('is_system', true)->whereNotNull('bitrix_id')->pluck('bitrix_id'));
    }

    public function keyForBitrix(string $model, int $bitrixId): ?string
    {
        return self::toStringOrNull($model::where('bitrix_id', $model::normalizeBitrixId($bitrixId))
            ->orderByDesc('is_system')
            ->ordered()
            ->value('key'));
    }

    public function bitrixIdForKey(string $model, ?string $key): ?int
    {
        $bitrixId = $key === null ? null : $model::where('key', $key)->value('bitrix_id');

        return self::toIntOrNull($bitrixId);
    }

    public function isUsed(TaskLookup $lookup): bool
    {
        return Task::withTrashed()->where($lookup::taskColumn(), $lookup->key)->exists();
    }

    public function findSystem(string $model, int $bitrixId): ?TaskLookup
    {
        return $model::where('is_system', true)->where('bitrix_id', $bitrixId)->first();
    }

    public function nextSort(string $model): int
    {
        return (self::toIntOrNull($model::max('sort')) ?? 0) + 1;
    }

    public function save(TaskLookup $lookup): void
    {
        $lookup->save();
    }
}
