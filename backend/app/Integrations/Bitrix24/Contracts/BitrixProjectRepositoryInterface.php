<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\User;

/**
 * Projects as Bitrix24 workgroups (sonet_group.*), through the gateway.
 * Reads and writes only; ProjectService decides when and whether.
 */
interface BitrixProjectRepositoryInterface
{
    /**
     * Creates the workgroup as a project, with the employee's token.
     *
     * @return int The new group's ID.
     */
    public function createGroup(User $actor, Project $project): int;

    /**
     * Sends the given columns of the project, and its archived state when
     * $closed is not null, with the employee's token.
     *
     * @param  list<string>  $columns  Our project columns (title, description, start_date, due_date).
     */
    public function updateGroup(User $actor, Project $project, array $columns, ?bool $closed): void;

    /** Closes (archives) the workgroup, with the employee's token. */
    public function closeGroup(User $actor, int $groupId): void;

    /** Adds the member with the service account (Bitrix lets only administrators do it). */
    public function addMember(int $groupId, int $bitrixUserId, ProjectMemberRole $role): void;

    /** Removes the member with the service account. */
    public function removeMember(int $groupId, int $bitrixUserId): void;

    /**
     * Which of the given columns Bitrix keeps (the others are ours only).
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function syncedColumns(array $columns): array;
}
