<?php

declare(strict_types=1);

namespace App\Models;

/**
 * @property bool $is_final A closing status: a task in it is finished (completed_at set).
 */
class TaskStatus extends TaskLookup
{
    /** Bitrix status 1 ("New") is legacy; Bitrix itself shows it as Pending (2). */
    private const BITRIX_NEW = 1;

    private const BITRIX_PENDING = 2;

    protected $fillable = ['key', 'name', 'bitrix_id', 'is_final', 'sort', 'color'];

    public static function taskColumn(): string
    {
        return 'status';
    }

    public static function normalizeBitrixId(int $bitrixId): int
    {
        return $bitrixId === self::BITRIX_NEW ? self::BITRIX_PENDING : $bitrixId;
    }

    protected function casts(): array
    {
        return parent::casts() + ['is_final' => 'boolean'];
    }
}
