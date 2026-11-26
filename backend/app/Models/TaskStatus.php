<?php

declare(strict_types=1);

namespace App\Models;

class TaskStatus extends TaskLookup
{
    /** Bitrix status 1 ("New") is legacy; Bitrix itself shows it as Pending (2). */
    private const BITRIX_NEW = 1;

    private const BITRIX_PENDING = 2;

    protected $fillable = ['key', 'name', 'bitrix_id', 'is_final', 'sort', 'color'];

    /** @var list<string>|null */
    private static ?array $finalKeys = null;

    public static function taskColumn(): string
    {
        return 'status';
    }

    public static function normalizeBitrixId(int $bitrixId): int
    {
        return $bitrixId === self::BITRIX_NEW ? self::BITRIX_PENDING : $bitrixId;
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

    protected function casts(): array
    {
        return parent::casts() + ['is_final' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn (): null => self::$finalKeys = null);
        static::deleted(fn (): null => self::$finalKeys = null);
    }
}
