<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Bitrix\SyncBitrixProjectAction;
use App\Actions\Bitrix\SyncBitrixTaskAction;
use App\Actions\Bitrix\SyncBitrixUserAction;
use App\Enums\BitrixSyncOperation;
use App\Enums\TaskMemberRole;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixDeletionGuardInterface;
use App\Integrations\Bitrix24\Contracts\BitrixEchoGuardInterface;
use App\Integrations\Bitrix24\Contracts\BitrixProjectMapperInterface;
use App\Integrations\Bitrix24\Contracts\BitrixRecordFetcherInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTaskMapperInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTaskResolverInterface;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Contracts\BitrixSyncFailureServiceInterface;
use App\Services\Contracts\BitrixSyncServiceInterface;
use App\Services\Contracts\ProjectServiceInterface;
use App\Services\Contracts\TaskChatServiceInterface;
use App\Services\Contracts\TaskServiceInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class BitrixSyncService implements BitrixSyncServiceInterface
{
    public function __construct(
        private BitrixRecordFetcherInterface $fetch,
        private BitrixTaskResolverInterface $taskResolver,
        private SyncBitrixTaskAction $taskSync,
        private BitrixTaskMapperInterface $taskMapper,
        private SyncBitrixProjectAction $projectSync,
        private SyncBitrixUserAction $userSync,
        private ConnectionInterface $database,
        private BitrixProjectMapperInterface $projectMapper,
        private BitrixEchoGuardInterface $echo,
        private BitrixDeletionGuardInterface $deletions,
        private TaskServiceInterface $tasks,
        private ProjectServiceInterface $projects,
        private TaskChatServiceInterface $chat,
        private BitrixSyncFailureServiceInterface $failures,
    ) {}

    public function syncTask(int $bitrixTaskId): void
    {
        $this->pullTask($bitrixTaskId);
        // Done: a dead letter of this sync, if one is open, is closed (step 5.2).
        $this->failures->resolve(BitrixSyncOperation::Task, (string) $bitrixTaskId);
    }

    public function syncProject(int $bitrixGroupId): void
    {
        $this->pullProject($bitrixGroupId);
        // Done: a dead letter of this sync, if one is open, is closed (step 5.2).
        $this->failures->resolve(BitrixSyncOperation::Project, (string) $bitrixGroupId);
    }

    public function syncTaskChat(int $bitrixTaskId): void
    {
        $this->pullTaskChat($bitrixTaskId);
        // Done: a dead letter of this sync, if one is open, is closed (step 5.2).
        $this->failures->resolve(BitrixSyncOperation::TaskChat, (string) $bitrixTaskId);
    }

    public function refreshTask(int $bitrixTaskId): void
    {
        $row = $this->fetch->task($bitrixTaskId)
            ?? throw BitrixException::cannotSync('The task is no longer in Bitrix24.');

        $members = $this->taskMapper->members($row);
        $this->importMissingUsers([...$members[TaskMemberRole::Accomplice->value] ?? [], ...$members[TaskMemberRole::Auditor->value] ?? []]);
        $this->taskSync->handle($row);
    }

    private function pullTask(int $bitrixTaskId): void
    {
        $row = $this->fetch->task($bitrixTaskId);

        if ($row === null) {
            $this->taskGoneFromBitrix($bitrixTaskId);

            return;
        }

        // The task may point at people or a group we haven't seen yet.
        $members = $this->taskMapper->members($row);
        $this->importMissingUsers([
            $this->taskMapper->creatorBitrixId($row), $this->taskMapper->responsibleBitrixId($row),
            ...$members[TaskMemberRole::Accomplice->value] ?? [], ...$members[TaskMemberRole::Auditor->value] ?? [],
        ]);

        $groupId = $this->taskMapper->groupBitrixId($row);
        if ($groupId !== 0 && $this->projects->idByBitrixGroupId($groupId) === null) {
            $this->syncProject($groupId);
        }

        // Our own change coming back: already saved here.
        $attributes = $this->taskResolver->resolve($row);
        if ($attributes !== null && $this->echo->isTaskEcho($bitrixTaskId, $attributes, $members)) {
            return;
        }

        $this->taskSync->handle($row);
    }

    private function pullProject(int $bitrixGroupId): void
    {
        $group = $this->fetch->group($bitrixGroupId);

        if ($group === null) {
            $this->projectGoneFromBitrix($bitrixGroupId);

            return;
        }

        // Our own change coming back: already saved here.
        if ($this->echo->isProjectEcho($bitrixGroupId, $this->projectMapper->attributes($group), $this->projectMapper->isClosed($group))) {
            return;
        }

        $members = $this->fetch->members($bitrixGroupId);
        $this->importMissingUsers([$this->projectMapper->ownerBitrixId($group), ...array_keys($this->projectMapper->members($members))]);

        $this->database->transaction(fn (): ?Project => $this->projectSync->handle($group, $members));
    }

    private function pullTaskChat(int $bitrixTaskId): void
    {
        $task = $this->tasks->findByBitrixId($bitrixTaskId);

        if ($task instanceof Task && $task->isInBitrix()) {
            $this->chat->refreshFromBitrix($task);
        }
    }

    /**
     * People a record points at that we haven't imported yet, each saved in
     * its own transaction.
     *
     * @param  list<int>  $bitrixIds
     */
    private function importMissingUsers(array $bitrixIds): void
    {
        foreach ($this->fetch->missingUsers($bitrixIds) as $row) {
            $this->database->transaction(fn (): ?User => $this->userSync->handle($row));
        }
    }

    /**
     * Deleted in Bitrix: delete it here too, unless it is the echo of our own
     * delete. Only an admin service account sees every task, so "not found"
     * proves nothing otherwise.
     */
    private function taskGoneFromBitrix(int $bitrixTaskId): void
    {
        if ($this->echo->isTaskEcho($bitrixTaskId, null) || ! $this->deletions->serviceAccountIsAdmin()) {
            return;
        }

        $task = $this->tasks->findByBitrixId($bitrixTaskId);
        if ($task instanceof Task) {
            $this->tasks->removeGoneFromBitrix($task);
            $this->deletions->recordDeleted('tasks', [$bitrixTaskId], 'event');
        }
    }

    /** The same for a workgroup. */
    private function projectGoneFromBitrix(int $bitrixGroupId): void
    {
        if ($this->echo->isProjectEcho($bitrixGroupId, null, false) || ! $this->deletions->serviceAccountIsAdmin()) {
            return;
        }

        $project = $this->projects->findByBitrixGroupId($bitrixGroupId);
        if ($project instanceof Project) {
            $this->projects->removeGoneFromBitrix($project);
            $this->deletions->recordDeleted('groups', [$bitrixGroupId], 'event');
        }
    }
}
