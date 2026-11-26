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
    use ReadsInput;

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
}
