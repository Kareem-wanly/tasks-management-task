<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TaskLookup\SaveTaskLookupDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\TaskLookup\LookupFromBitrixException;
use App\Exceptions\TaskLookup\LookupInUseException;
use App\Integrations\Bitrix24\BitrixPayload;
use App\Integrations\Bitrix24\Contracts\BitrixTaskRepositoryInterface;
use App\Models\TaskLookup;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use App\Services\Contracts\TaskLookupServiceInterface;
use Illuminate\Support\Collection;

/**
 * Task statuses and priorities. Values from Bitrix (is_system) keep their
 * key and Bitrix link; only our own values can be deleted.
 */
final readonly class TaskLookupService implements TaskLookupServiceInterface
{
    public function __construct(
        private TaskLookupRepositoryInterface $lookups,
        private BitrixTaskRepositoryInterface $bitrixTasks,
    ) {}

    /**
     * @param  class-string<TaskLookup>  $model
     * @return Collection<int, TaskLookup>
     */
    public function list(string $model): Collection
    {
        return $this->lookups->ordered($model);
    }

    /**
     * @param  class-string<TaskLookup>  $model
     */
    public function create(string $model, SaveTaskLookupDTO $data): TaskLookup
    {
        return $this->lookups->create($model, $data->toArray());
    }

    public function update(TaskLookup $lookup, SaveTaskLookupDTO $data): TaskLookup
    {
        $attributes = $data->toArray();

        // The Bitrix link of a Bitrix value is fixed; the sync command owns it.
        if ($lookup->is_system) {
            unset($attributes['bitrix_id']);
        }

        return $this->lookups->update($lookup, $attributes);
    }

    public function delete(TaskLookup $lookup): void
    {
        if ($lookup->is_system) {
            throw new LookupFromBitrixException;
        }

        if ($this->lookups->isUsed($lookup)) {
            throw new LookupInUseException;
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

    public function syncFromBitrix(): array
    {
        $fields = BitrixPayload::of($this->bitrixTasks->fieldDefinitions(), 'tasks.task.getFields');

        if (! $fields->has('STATUS.values') || ! $fields->has('PRIORITY.values')) {
            throw BitrixException::cannotSync('Bitrix24 did not return STATUS / PRIORITY values.');
        }

        return [
            ...$this->syncValues(TaskStatus::class, 'status', $fields->strings('STATUS.values')),
            ...$this->syncValues(TaskPriority::class, 'priority', $fields->strings('PRIORITY.values')),
        ];
    }

    /** @param  class-string<TaskLookup>  $model */
    public function keyForBitrix(string $model, int $bitrixId): ?string
    {
        return $this->lookups->keyForBitrix($model, $bitrixId);
    }

    /** @param  class-string<TaskLookup>  $model */
    public function bitrixIdForKey(string $model, ?string $key): ?int
    {
        return $this->lookups->bitrixIdForKey($model, $key);
    }

    /**
     * @param  class-string<TaskLookup>  $model
     * @param  array<int|string, string>  $values  Bitrix ID => name
     * @return list<array{string, int, string, string, string}>
     */
    private function syncValues(string $model, string $type, array $values): array
    {
        $rows = [];

        foreach ($values as $bitrixId => $name) {
            $bitrixId = (int) $bitrixId;
            $lookup = $this->lookups->findSystem($model, $bitrixId);

            if (! $lookup instanceof TaskLookup) {
                $lookup = new $model(['key' => "bitrix_{$type}_{$bitrixId}", 'bitrix_id' => $bitrixId, 'color' => 'neutral']);
                $lookup->is_system = true;
                $lookup->sort = $this->lookups->nextSort($model);
                $change = 'added';
            } else {
                $change = $lookup->name === $name ? 'unchanged' : 'renamed';
            }

            $lookup->name = $name;
            $this->lookups->save($lookup);

            $rows[] = [$type, $bitrixId, $lookup->key, $name, $change];
        }

        return $rows;
    }
}
