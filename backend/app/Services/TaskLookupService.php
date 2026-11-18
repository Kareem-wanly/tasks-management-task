<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\TaskLookup;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Task statuses and priorities. Values from Bitrix (is_system) keep their
 * key and Bitrix link; only our own values can be deleted.
 */
class TaskLookupService
{
    public function __construct(private readonly TaskLookupRepositoryInterface $lookups) {}

    /** @param  class-string<TaskLookup>  $model */
    public function list(string $model): Collection
    {
        return $this->lookups->ordered($model);
    }

    /**
     * @param  class-string<TaskLookup>  $model
     * @param  array<string, mixed>  $data
     */
    public function create(string $model, array $data): TaskLookup
    {
        return $this->lookups->create($model, $data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(TaskLookup $lookup, array $data): TaskLookup
    {
        // The Bitrix link of a Bitrix value is fixed; the sync command owns it.
        if ($lookup->is_system) {
            unset($data['bitrix_id']);
        }

        return $this->lookups->update($lookup, $data);
    }

    public function delete(TaskLookup $lookup): void
    {
        if ($lookup->is_system) {
            throw new BusinessRuleException('Values that come from Bitrix24 cannot be deleted.');
        }

        if ($this->lookups->isUsed($lookup)) {
            throw new BusinessRuleException('This value is used by tasks and cannot be deleted.');
        }

        $this->lookups->delete($lookup);
    }

    /**
     * @param  class-string<TaskLookup>  $model
     * @return list<int>
     */
    public function bitrixIdsForCustomValues(string $model): array
    {
        return $this->lookups->systemBitrixIds($model);
    }
}
