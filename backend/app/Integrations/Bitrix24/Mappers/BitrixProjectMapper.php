<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Mappers;

use App\Enums\ProjectMemberRole;

/**
 * Turns a Bitrix24 workgroup (socialnetwork.api.workgroup.list, camelCase
 * keys) and its members (sonet_group.user.get) into our projects columns.
 */
final class BitrixProjectMapper
{
    /** Bitrix member role => project_user.role. Other roles (e.g. pending requests) are not members. */
    public const ROLES = [
        'A' => ProjectMemberRole::Owner->value,
        'E' => ProjectMemberRole::Moderator->value,
        'K' => ProjectMemberRole::Member->value,
    ];

    /**
     * The fields workgroup.list must select for this mapper.
     */
    public const SELECT = ['ID', 'NAME', 'DESCRIPTION', 'OWNER_ID', 'CLOSED', 'PROJECT_DATE_START', 'PROJECT_DATE_FINISH'];

    /**
     * Project columns Bitrix owns. Status is left out: only the archived
     * state comes from Bitrix, see isClosed().
     *
     * @return array<string, mixed>
     */
    public function attributes(array $group): array
    {
        $name = trim((string) ($group['name'] ?? ''));
        $description = trim((string) ($group['description'] ?? ''));

        return [
            'bitrix_group_id' => (int) $group['id'],
            'title' => $name !== '' ? $name : "Bitrix group {$group['id']}",
            'description' => $description !== '' ? $description : null,
            'start_date' => $this->date($group['projectDateStart'] ?? null),
            'due_date' => $this->date($group['projectDateFinish'] ?? null),
        ];
    }

    public function ownerBitrixId(array $group): int
    {
        return (int) ($group['ownerId'] ?? 0);
    }

    public function isClosed(array $group): bool
    {
        return ($group['closed'] ?? 'N') === 'Y';
    }

    /**
     * @return array<int, string> Bitrix user ID => project_user.role
     */
    public function members(array $members): array
    {
        $roles = [];

        foreach ($members as $member) {
            $role = self::ROLES[$member['ROLE'] ?? ''] ?? null;

            if ($role !== null) {
                $roles[(int) $member['USER_ID']] = $role;
            }
        }

        return $roles;
    }

    /**
     * Project dates are calendar days, stored by Bitrix as midnight in the
     * portal's timezone and returned in UTC: 15 Nov at +03:00 comes back as
     * 2026-11-14T21:00:00+00:00. Rounding to the nearest midnight gives the
     * day back for any timezone between -12:00 and +12:00.
     */
    private function date(mixed $value): ?string
    {
        return is_string($value) && $value !== ''
            ? \Illuminate\Support\Facades\Date::parse($value)->utc()->addHours(12)->toDateString()
            : null;
    }
}
