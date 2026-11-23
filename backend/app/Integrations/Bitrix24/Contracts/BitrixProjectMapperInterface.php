<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

/** Turns a Bitrix24 workgroup (workgroup.list) into our projects columns. */
interface BitrixProjectMapperInterface
{
    /**
     * Project columns Bitrix owns. Status is left out: only the archived
     * state comes from Bitrix, see isClosed().
     *
     * @param  array<string, mixed>  $group
     * @return array{bitrix_group_id: int, title: string, description: string|null, start_date: string|null, due_date: string|null}
     */
    public function attributes(array $group): array;

    /** @param  array<string, mixed>  $group */
    public function ownerBitrixId(array $group): int;

    /** @param  array<string, mixed>  $group */
    public function isClosed(array $group): bool;

    /**
     * @param  list<array<string, mixed>>  $members
     * @return array<int, string> Bitrix user ID => project_user.role
     */
    public function members(array $members): array;
}
