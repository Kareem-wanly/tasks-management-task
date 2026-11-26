<?php

declare(strict_types=1);

namespace App\DTOs\Concerns;

/**
 * Partial input (PATCH-like updates): a field the client did not send is left
 * alone, while a field sent as null clears it. The DTO keeps the input names it
 * was built from in its `$provided` property.
 */
trait TracksProvidedFields
{
    public function has(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    /**
     * @param  array<string, mixed>  $values  Every field, keyed by its input name.
     * @return array<string, mixed> Only the fields the client sent.
     */
    private function onlyProvided(array $values): array
    {
        return array_intersect_key($values, array_flip($this->provided));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private static function boolOrNull(mixed $value): ?bool
    {
        return $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
