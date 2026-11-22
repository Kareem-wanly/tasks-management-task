<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Mappers;

use Illuminate\Support\Str;

/**
 * Turns a Bitrix24 user (user.get / user.current) into our users columns.
 */
final class BitrixUserMapper
{
    /**
     * Columns Bitrix owns. Name and email are left out: they need lookups
     * (fallbacks, uniqueness) that BitrixUserSync does.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function attributes(array $row): array
    {
        $attributes = [
            'bitrix_user_id' => (int) $row['ID'],
            'avatar_url' => $this->nullIfBlank($row['PERSONAL_PHOTO'] ?? null),
            'position' => $this->nullIfBlank($row['WORK_POSITION'] ?? null),
            'bitrix_synced_at' => now(),
        ];

        // user.current may leave these out; don't wipe what we have.
        if (array_key_exists('ACTIVE', $row)) {
            $attributes['is_active'] = $this->isActive($row['ACTIVE']);
        }

        if (array_key_exists('UF_DEPARTMENT', $row)) {
            $attributes['bitrix_department_ids'] = array_values(array_map('intval', (array) $row['UF_DEPARTMENT']));
        }

        return $attributes;
    }

    /** @param  array<string, mixed>  $row */
    public function name(array $row): ?string
    {
        return $this->nullIfBlank(trim(($row['NAME'] ?? '').' '.($row['LAST_NAME'] ?? '')));
    }

    /** @param  array<string, mixed>  $row */
    public function email(array $row): ?string
    {
        return $this->nullIfBlank(Str::lower(trim((string) ($row['EMAIL'] ?? ''))));
    }

    /**
     * Bitrix sends true/false in newer responses and "Y"/"N" in older ones.
     */
    private function isActive(mixed $value): bool
    {
        return in_array($value, [true, 'Y', '1', 1], true);
    }

    private function nullIfBlank(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
