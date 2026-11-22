<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Enums\ProjectMemberRole;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixPayload;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Contracts\BitrixProjectRepositoryInterface;
use App\Integrations\Bitrix24\Mappers\BitrixProjectMapper;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonInterface;

final readonly class BitrixProjectRepository implements BitrixProjectRepositoryInterface
{
    /** Our columns => sonet_group.create/update fields. */
    private const FIELDS = [
        'title' => 'NAME',
        'description' => 'DESCRIPTION',
        'start_date' => 'PROJECT_DATE_START',
        'due_date' => 'PROJECT_DATE_FINISH',
    ];

    /** project_user.role => Bitrix member role. The owner is set by Bitrix itself. */
    private const MEMBER_ROLES = [ProjectMemberRole::Moderator->value => 'E', ProjectMemberRole::Member->value => 'K'];

    /** A plain participant: what sonet_group.user.add makes. */
    private const PARTICIPANT = 'K';

    public function __construct(private BitrixGatewayInterface $bitrix) {}

    public function createGroup(User $actor, Project $project): int
    {
        $fields = $this->fields($project, array_keys(self::FIELDS));
        $fields['PROJECT'] = 'Y';

        $groupId = BitrixPayload::of($this->bitrix->callAsUser($actor, 'sonet_group.create', $fields), 'sonet_group.create')->intOrNull('result') ?? 0;

        return $groupId ?: throw BitrixException::cannotSync('Bitrix24 did not return the new project.');
    }

    public function updateGroup(User $actor, Project $project, array $columns, ?bool $closed): void
    {
        $fields = $this->fields($project, $columns);
        if ($closed !== null) {
            $fields['CLOSED'] = $closed ? 'Y' : 'N';
        }

        $this->bitrix->callAsUser($actor, 'sonet_group.update', ['GROUP_ID' => $project->bitrix_group_id, ...$fields]);
    }

    public function closeGroup(User $actor, int $groupId): void
    {
        $this->bitrix->callAsUser($actor, 'sonet_group.update', ['GROUP_ID' => $groupId, 'CLOSED' => 'Y']);
    }

    public function addMember(int $groupId, int $bitrixUserId, ProjectMemberRole $role): void
    {
        $this->bitrix->callAsService('sonet_group.user.add', ['GROUP_ID' => $groupId, 'USER_ID' => $bitrixUserId]);

        // Added as a participant; anything else is a role change.
        if (($bitrixRole = self::MEMBER_ROLES[$role->value] ?? self::PARTICIPANT) !== self::PARTICIPANT) {
            $this->bitrix->callAsService('sonet_group.user.update', [
                'GROUP_ID' => $groupId,
                'USER_ID' => $bitrixUserId,
                'ROLE' => $bitrixRole,
            ]);
        }
    }

    public function removeMember(int $groupId, int $bitrixUserId): void
    {
        $this->bitrix->callAsService('sonet_group.user.delete', ['GROUP_ID' => $groupId, 'USER_ID' => $bitrixUserId]);
    }

    public function syncedColumns(array $columns): array
    {
        return array_values(array_intersect($columns, array_keys(self::FIELDS)));
    }

    public function eachGroupPage(callable $onPage): int
    {
        return $this->bitrix->eachPageAsService('socialnetwork.api.workgroup.list', [
            'select' => BitrixProjectMapper::SELECT,
            'order' => ['ID' => 'ASC'],
            // Without it the list is limited to groups the service user can see.
            'params' => ['IS_ADMIN' => 'Y'],
        ], function (array $page, int $total) use ($onPage): void {
            $onPage(BitrixPayload::of($page, 'socialnetwork.api.workgroup.list')->rows('workgroups'), $total);
        });
    }

    public function membersOf(array $groups): array
    {
        $groupIds = array_map(fn (array $group): int => BitrixPayload::of($group, 'socialnetwork.api.workgroup.list')->int('id'), $groups);

        $commands = [];
        foreach ($groupIds as $groupId) {
            $commands["group_{$groupId}"] = ['sonet_group.user.get', ['ID' => $groupId]];
        }

        $batch = BitrixPayload::of($this->bitrix->batchAsService($commands), 'sonet_group.user.get');
        $batch->failOnBatchError();

        $members = [];
        foreach ($groupIds as $groupId) {
            $members[$groupId] = $batch->rows("result.group_{$groupId}");
        }

        return $members;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    private function fields(Project $project, array $columns): array
    {
        $fields = [];

        foreach ($columns as $column) {
            $value = $project->{$column};
            // Empty clears the value in Bitrix.
            $fields[self::FIELDS[$column]] = $value instanceof CarbonInterface ? $value->toIso8601String() : (string) ($value ?? '');
        }

        return $fields;
    }
}
