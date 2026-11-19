<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\SyncCursorStatus;
use App\Models\BitrixSyncCursor;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentBitrixSyncCursorRepository implements BitrixSyncCursorRepositoryInterface
{
    public function forEntity(string $entity): BitrixSyncCursor
    {
        return BitrixSyncCursor::firstOrNew(['entity' => $entity]);
    }

    public function forEntities(array $entities): Collection
    {
        return BitrixSyncCursor::whereIn('entity', $entities)->get()->keyBy('entity');
    }

    public function start(string $entity): BitrixSyncCursor
    {
        $cursor = $this->forEntity($entity);
        $cursor->fill(['status' => SyncCursorStatus::Running, 'processed' => 0, 'total' => 0, 'error' => null, 'started_at' => now()])->save();

        return $cursor;
    }

    public function advance(BitrixSyncCursor $cursor, int $processed, int $total): void
    {
        $cursor->update(['processed' => $cursor->processed + $processed, 'total' => $total]);
    }

    public function complete(BitrixSyncCursor $cursor): void
    {
        $cursor->update(['status' => SyncCursorStatus::Completed, 'last_synced_at' => $cursor->started_at ?? now()]);
    }

    public function fail(string $entity, string $error): void
    {
        $this->forEntity($entity)->fill(['status' => SyncCursorStatus::Failed, 'error' => $error])->save();
    }
}
