<?php

namespace App\Repositories\Contracts;

use App\Models\BitrixSyncCursor;
use Illuminate\Support\Collection;

/**
 * Progress of each Bitrix24 import (users, projects, tasks, ...).
 */
interface BitrixSyncCursorRepositoryInterface
{
    /** The entity's cursor; a new, unsaved one when it never ran. */
    public function forEntity(string $entity): BitrixSyncCursor;

    /**
     * @param  list<string>  $entities
     * @return Collection<string, BitrixSyncCursor> By entity.
     */
    public function forEntities(array $entities): Collection;

    /** Marks a run as started, from zero. */
    public function start(string $entity): BitrixSyncCursor;

    public function advance(BitrixSyncCursor $cursor, int $processed, int $total): void;

    /**
     * Synced as of when the run started: a change made in Bitrix while it
     * ran may have been missed, so the next delta must still look at it.
     */
    public function complete(BitrixSyncCursor $cursor): void;

    /** Saves even a cursor that doesn't exist yet: the job may fail before it started. */
    public function fail(string $entity, string $error): void;
}
