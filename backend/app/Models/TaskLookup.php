<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared base of TaskStatus and TaskPriority: a row in a lookup table that
 * tasks reference by `key` (tasks.status / tasks.priority).
 */
abstract class TaskLookup extends Model
{
    /** Colour families the frontend knows (its badge "tones"). */
    public const COLORS = ['neutral', 'info', 'brand', 'success', 'warning', 'danger'];

    protected $fillable = ['key', 'name', 'bitrix_id', 'sort', 'color'];

    protected $hidden = ['created_at', 'updated_at'];

    /** The tasks column that holds this lookup's key. */
    abstract public static function taskColumn(): string;

    protected function casts(): array
    {
        return [
            'bitrix_id' => 'integer',
            'is_system' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /**
     * Our key for a Bitrix value. The Bitrix-imported row wins over a custom
     * value that only sends that Bitrix value (e.g. urgent → high).
     */
    public static function keyForBitrix(int $bitrixId): ?string
    {
        return static::where('bitrix_id', $bitrixId)
            ->orderByDesc('is_system')
            ->ordered()
            ->value('key');
    }

    public function isUsed(): bool
    {
        return Task::withTrashed()->where(static::taskColumn(), $this->key)->exists();
    }
}
