<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared base of TaskStatus and TaskPriority: a row in a lookup table that
 * tasks reference by `key` (tasks.status / tasks.priority).
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $color
 * @property int $sort
 * @property int|null $bitrix_id
 * @property bool $is_system A Bitrix24 value (synced by bitrix:sync-task-lookups), not one of ours.
 */
abstract class TaskLookup extends Model
{
    /** Colour families the frontend knows (its badge "tones"). */
    public const COLORS = ['neutral', 'info', 'brand', 'success', 'warning', 'danger'];

    protected $fillable = ['key', 'name', 'bitrix_id', 'sort', 'color'];

    protected $hidden = ['created_at', 'updated_at'];

    /** The tasks column that holds this lookup's key. */
    abstract public static function taskColumn(): string;

    /**
     * The Bitrix value to look a row up by. Statuses map Bitrix's legacy
     * "New" to Pending (TaskStatus); priorities use the value as it is.
     */
    public static function normalizeBitrixId(int $bitrixId): int
    {
        return $bitrixId;
    }

    protected function casts(): array
    {
        return [
            'bitrix_id' => 'integer',
            'is_system' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }
}
