<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Progress of a Bitrix24 import per entity (users, projects, tasks, ...).
 * Stored and queried through BitrixSyncCursorRepository.
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
}
