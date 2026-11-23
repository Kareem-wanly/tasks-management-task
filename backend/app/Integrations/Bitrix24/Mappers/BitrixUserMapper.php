<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Mappers;

use App\Integrations\Bitrix24\BitrixPayload;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Turns a Bitrix24 user (user.get / user.current) into our users columns.
 */
final class BitrixUserMapper
{
    /**
     * Columns Bitrix owns. Name and email are left out: they need lookups
     * (fallbacks, uniqueness) that SyncBitrixUserAction does.
     *
     * @param  array<string, mixed>  $row
     * @return array{bitrix_user_id: int, avatar_url: string|null, position: string|null, bitrix_synced_at: CarbonInterface, is_active?: bool, bitrix_department_ids?: list<int>}
     */
    public function attributes(array $row): array
    {
        $user = BitrixPayload::of($row, 'user.get');
        $attributes = [
            'bitrix_user_id' => $user->int('ID'),
            'avatar_url' => $this->nullIfBlank($row['PERSONAL_PHOTO'] ?? null),
            'position' => $this->nullIfBlank($row['WORK_POSITION'] ?? null),
            'bitrix_synced_at' => now(),
        ];

        // user.current may leave these out; don't wipe what we have.
        if (array_key_exists('ACTIVE', $row)) {
            $attributes['is_active'] = $this->isActive($row['ACTIVE']);
        }

        if (array_key_exists('UF_DEPARTMENT', $row)) {
            $attributes['bitrix_department_ids'] = $user->ints('UF_DEPARTMENT');
        }

        return $attributes;
    }

    /** @param  array<string, mixed>  $row */
    public function name(array $row): ?string
    {
        $user = BitrixPayload::of($row, 'user.get');

        return $this->nullIfBlank(trim(($user->stringOrNull('NAME') ?? '').' '.($user->stringOrNull('LAST_NAME') ?? '')));
    }

    /** @param  array<string, mixed>  $row */
    public function email(array $row): ?string
    {
        return $this->nullIfBlank(Str::lower(trim(BitrixPayload::of($row, 'user.get')->stringOrNull('EMAIL') ?? '')));
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
