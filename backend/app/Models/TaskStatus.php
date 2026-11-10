<?php

namespace App\Models;

class TaskStatus extends TaskLookup
{
    protected $fillable = ['key', 'name', 'bitrix_id', 'is_final', 'sort', 'color'];

    /** @var list<string>|null */
    private static ?array $finalKeys = null;

    public static function taskColumn(): string
    {
        return 'status';
    }

    protected function casts(): array
    {
        return parent::casts() + ['is_final' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$finalKeys = null);
        static::deleted(fn () => self::$finalKeys = null);
    }

    /**
     * Keys of the statuses that close a task (is_final). Cached for the process
     * because Task::is_overdue asks for every task in a list.
     *
     * @return list<string>
     */
    public static function finalKeys(): array
    {
        return self::$finalKeys ??= static::where('is_final', true)->pluck('key')->all();
    }
}
