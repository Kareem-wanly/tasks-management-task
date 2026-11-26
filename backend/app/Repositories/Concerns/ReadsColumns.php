<?php

declare(strict_types=1);

namespace App\Repositories\Concerns;

use UnexpectedValueException;

/**
 * Single column values (value(), pluck(), max()) come back untyped. These check
 * them: MySQL gives integers, some drivers numeric strings; anything else is a bug.
 */
trait ReadsColumns
{
    private static function toInt(mixed $value): int
    {
        return match (true) {
            is_int($value) => $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => throw new UnexpectedValueException('Expected an integer column value, got '.get_debug_type($value).'.'),
        };
    }

    private static function toIntOrNull(mixed $value): ?int
    {
        return $value === null ? null : self::toInt($value);
    }

    private static function toStringOrNull(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            default => throw new UnexpectedValueException('Expected a text column value, got '.get_debug_type($value).'.'),
        };
    }

    /**
     * @param  iterable<mixed>  $values
     * @return list<int>
     */
    private static function toIntList(iterable $values): array
    {
        $ints = [];
        foreach ($values as $value) {
            $ints[] = self::toInt($value);
        }

        return $ints;
    }
}
