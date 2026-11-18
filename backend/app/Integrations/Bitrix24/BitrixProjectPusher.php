<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Models\BitrixOutboundOp;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\BitrixOutboundOpRepositoryInterface;
use Carbon\CarbonInterface;

/**
 * Step 3.2: sends project changes made in our app to the linked Bitrix24
 * group. Called before the local save: when Bitrix refuses, it throws and
 * nothing is saved here.
 *
 * Group changes go with the employee's own token. Member changes go with
 * the service account, because Bitrix lets only administrators add
 * members or change their role; our own policy has already allowed them.
 */
class BitrixProjectPusher
{
    /** Our columns => sonet_group.create/update fields. */
    private const FIELDS = [
        'title' => 'NAME',
        'description' => 'DESCRIPTION',
        'start_date' => 'PROJECT_DATE_START',
        'due_date' => 'PROJECT_DATE_FINISH',
    ];

    /** project_user.role => Bitrix member role. The owner is set by Bitrix itself. */
    private const MEMBER_ROLES = ['moderator' => 'E', 'member' => 'K'];

    public function __construct(
        private readonly BitrixClient $bitrix,
        private readonly BitrixOutboundOpRepositoryInterface $ops,
        private readonly BitrixFingerprint $fingerprints,
    ) {}

    /**
     * New projects are Bitrix projects; the creator becomes the group owner.
     */
    public function create(User $actor, Project $project): void
    {
        $this->ensureLinked($actor);

        $fields = $this->fields($project, array_keys(self::FIELDS));
        $fields['PROJECT'] = 'Y';

        $groupId = (int) ($this->bitrix->callAsUser($actor, 'sonet_group.create', $fields)['result'] ?? 0);
        $project->bitrix_group_id = $groupId ?: throw BitrixException::cannotSync('Bitrix24 did not return the new project.');
        $project->bitrix_synced_at = now();
        $this->ops->record(BitrixOutboundOp::PROJECT, $groupId, 'create', $this->fingerprints->project($project), $actor);
    }

    /**
     * Sends the project's unsaved changes (its dirty attributes). Only the
     * archived state of `status` exists in Bitrix (CLOSED).
     */
    public function update(User $actor, Project $project): void
    {
        if (! $project->bitrix_group_id) {
            return;
        }

        $fields = $this->fields($project, array_keys(array_intersect_key($project->getDirty(), self::FIELDS)));

        $wasArchived = $project->getOriginal('status') === 'archived';
        if ($project->isDirty('status') && $wasArchived !== ($project->status === 'archived')) {
            $fields['CLOSED'] = $wasArchived ? 'N' : 'Y';
        }

        if ($fields === []) {
            return;
        }

        $this->ensureLinked($actor);
        $this->bitrix->callAsUser($actor, 'sonet_group.update', ['GROUP_ID' => $project->bitrix_group_id, ...$fields]);
        $project->bitrix_synced_at = now();
        $this->ops->record(BitrixOutboundOp::PROJECT, $project->bitrix_group_id, 'update', $this->fingerprints->project($project), $actor);
    }

    /**
     * Deleting here only archives the Bitrix group: deleting a group there
     * is permanent and takes its feed and files with it.
     */
    public function delete(User $actor, Project $project): void
    {
        if (! $project->bitrix_group_id) {
            return;
        }

        $this->ensureLinked($actor);
        $this->bitrix->callAsUser($actor, 'sonet_group.update', ['GROUP_ID' => $project->bitrix_group_id, 'CLOSED' => 'Y']);
        $this->ops->record(BitrixOutboundOp::PROJECT, $project->bitrix_group_id, 'delete', null, $actor);
    }

    public function addMember(User $actor, Project $project, User $member, string $role): void
    {
        if (! $project->bitrix_group_id) {
            return;
        }

        $this->ensureLinked($actor);
        $memberId = $this->bitrixUserId($member);

        $this->bitrix->callAsService('sonet_group.user.add', ['GROUP_ID' => $project->bitrix_group_id, 'USER_ID' => $memberId]);

        // Added as a participant (K); anything else is a role change.
        if (($bitrixRole = self::MEMBER_ROLES[$role] ?? 'K') !== 'K') {
            $this->bitrix->callAsService('sonet_group.user.update', [
                'GROUP_ID' => $project->bitrix_group_id,
                'USER_ID' => $memberId,
                'ROLE' => $bitrixRole,
            ]);
        }
    }

    public function removeMember(User $actor, Project $project, User $member): void
    {
        if (! $project->bitrix_group_id) {
            return;
        }

        $this->ensureLinked($actor);
        $this->bitrix->callAsService('sonet_group.user.delete', [
            'GROUP_ID' => $project->bitrix_group_id,
            'USER_ID' => $this->bitrixUserId($member),
        ]);
    }

    private function ensureLinked(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }

    private function bitrixUserId(User $user): int
    {
        return $user->bitrix_user_id
            ?? throw BitrixException::cannotSync("{$user->name} is not a Bitrix24 user, so they cannot be added to this project.");
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
