<?php

declare(strict_types=1);

namespace App\DTOs\Concerns;

use InvalidArgumentException;

/**
 * Validated input keeps the types the client sent: JSON numbers, query-string
 * text. These accept both and refuse anything else, instead of a silent cast
 * (e.g. (int) 'abc' === 0).
 */
trait ReadsInput
{
    private static function requiredText(mixed $value): string
    {
        return self::textOrNull($value) ?? throw new InvalidArgumentException('Expected text, got null.');
    }

    private static function textOrNull(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            default => throw new InvalidArgumentException('Expected text, got '.get_debug_type($value).'.'),
        };
    }

    /** Like textOrNull(), with an empty value meaning "not given" (filters). */
    private static function filledTextOrNull(mixed $value): ?string
    {
        $text = self::textOrNull($value);

        return $text === '' ? null : $text;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return match (true) {
            $value === null => null,
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => throw new InvalidArgumentException('Expected a whole number, got '.get_debug_type($value).'.'),
        };
    }

    /** What Laravel's boolean rule accepts: true, false, 1, 0, "1", "0" (and "true"/"false" as text). */
    private static function boolOrNull(mixed $value): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        return (is_int($value) || is_string($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null)
            ?? throw new InvalidArgumentException('Expected true or false, got '.get_debug_type($value).'.');
    }
}
