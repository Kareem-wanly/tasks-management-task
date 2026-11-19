<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncCursorStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Progress of a Bitrix24 import per entity (users, projects, tasks, ...).
 * Stored and queried through BitrixSyncCursorRepository.
 *
 * @property string $entity
 * @property SyncCursorStatus|null $status
 * @property int $processed
 * @property int $total
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $last_synced_at Start of the last run that completed.
 */
class BitrixSyncCursor extends Model
{
    protected $fillable = ['entity', 'status', 'processed', 'total', 'error', 'started_at', 'last_synced_at'];

    protected function casts(): array
    {
        return [
            'status' => SyncCursorStatus::class,
            'processed' => 'integer',
            'total' => 'integer',
            'started_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
