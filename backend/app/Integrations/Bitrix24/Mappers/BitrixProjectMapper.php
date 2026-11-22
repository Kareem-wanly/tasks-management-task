<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Mappers;

use App\Enums\ProjectMemberRole;
use App\Integrations\Bitrix24\BitrixPayload;
use Carbon\CarbonImmutable;

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
     * @param  array<string, mixed>  $group
     * @return array{bitrix_group_id: int, title: string, description: string|null, start_date: string|null, due_date: string|null}
     */
    public function attributes(array $group): array
    {
        $row = BitrixPayload::of($group, 'socialnetwork.api.workgroup.list');
        $bitrixId = $row->int('id');
        $name = trim($row->stringOrNull('name') ?? '');
        $description = trim($row->stringOrNull('description') ?? '');

        return [
            'bitrix_group_id' => $bitrixId,
            'title' => $name !== '' ? $name : "Bitrix group {$bitrixId}",
            'description' => $description !== '' ? $description : null,
            'start_date' => $this->day($row->dateOrNull('projectDateStart')),
            'due_date' => $this->day($row->dateOrNull('projectDateFinish')),
        ];
    }

    /** @param  array<string, mixed>  $group */
    public function ownerBitrixId(array $group): int
    {
        return BitrixPayload::of($group, 'socialnetwork.api.workgroup.list')->intOrNull('ownerId') ?? 0;
    }

    /** @param  array<string, mixed>  $group */
    public function isClosed(array $group): bool
    {
        return ($group['closed'] ?? 'N') === 'Y';
    }

    /**
     * @param  list<array<string, mixed>>  $members
     * @return array<int, string> Bitrix user ID => project_user.role
     */
    public function members(array $members): array
    {
        $roles = [];

        foreach ($members as $member) {
            $row = BitrixPayload::of($member, 'sonet_group.user.get');
            $role = self::ROLES[$row->stringOrNull('ROLE') ?? ''] ?? null;

            if ($role !== null) {
                $roles[$row->int('USER_ID')] = $role;
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
    private function day(?CarbonImmutable $midnight): ?string
    {
        return $midnight?->utc()->addHours(12)->toDateString();
    }
}
