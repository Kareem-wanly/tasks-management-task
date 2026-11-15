<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Progress of a Bitrix24 import per entity (users, projects, tasks).
 */
class BitrixSyncCursor extends Model
{
    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = ['entity', 'status', 'processed', 'total', 'error', 'started_at', 'last_synced_at'];

    protected function casts(): array
    {
        return [
            'processed' => 'integer',
            'total' => 'integer',
            'started_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public static function for(string $entity): self
    {
        return static::firstOrNew(['entity' => $entity]);
    }

    public static function start(string $entity): self
    {
        $cursor = static::for($entity);
        $cursor->fill(['status' => self::RUNNING, 'processed' => 0, 'total' => 0, 'error' => null, 'started_at' => now()])->save();

        return $cursor;
    }

    public function advance(int $processed, int $total): void
    {
        $this->update(['processed' => $this->processed + $processed, 'total' => $total]);
    }

    public function complete(): void
    {
        $this->update(['status' => self::COMPLETED, 'last_synced_at' => now()]);
    }

    /** Saves even a row that doesn't exist yet: the job may fail before it started. */
    public function fail(string $error): void
    {
        $this->fill(['status' => self::FAILED, 'error' => $error])->save();
    }
}
