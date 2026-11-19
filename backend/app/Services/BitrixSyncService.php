<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskMemberRole;
use App\Integrations\Bitrix24\BitrixDeletionGuard;
use App\Integrations\Bitrix24\BitrixEchoGuard;
use App\Integrations\Bitrix24\BitrixProjectSync;
use App\Integrations\Bitrix24\BitrixRecordFetcher;
use App\Integrations\Bitrix24\BitrixTaskSync;
use App\Integrations\Bitrix24\Mappers\BitrixProjectMapper;
use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\BitrixSyncServiceInterface;
use App\Services\Contracts\TaskChatServiceInterface;

final readonly class BitrixSyncService implements BitrixSyncServiceInterface
{
    public function __construct(
        private BitrixRecordFetcher $fetch,
        private BitrixTaskSync $taskSync,
        private BitrixTaskMapper $taskMapper,
        private BitrixProjectSync $projectSync,
        private BitrixProjectMapper $projectMapper,
        private BitrixEchoGuard $echo,
        private BitrixDeletionGuard $deletions,
        private TaskRepositoryInterface $tasks,
        private ProjectRepositoryInterface $projects,
        private TaskChatServiceInterface $chat,
    ) {}

    public function syncTask(int $bitrixTaskId): void
    {
        $row = $this->fetch->task($bitrixTaskId);

        if ($row === null) {
            $this->taskGoneFromBitrix($bitrixTaskId);

            return;
        }

        // The task may point at people or a group we haven't seen yet.
        $members = $this->taskMapper->members($row);
        $this->fetch->importMissingUsers([
            $this->taskMapper->creatorBitrixId($row), $this->taskMapper->responsibleBitrixId($row),
            ...$members[TaskMemberRole::Accomplice->value] ?? [], ...$members[TaskMemberRole::Auditor->value] ?? [],
        ]);

        $groupId = $this->taskMapper->groupBitrixId($row);
        if ($groupId !== 0 && $this->projects->idByBitrixGroupId($groupId) === null) {
            $this->syncProject($groupId);
        }

        // Our own change coming back: already saved here.
        $attributes = $this->taskSync->resolve($row);
        if ($attributes !== null && $this->echo->isTaskEcho($bitrixTaskId, $attributes, $members)) {
            return;
        }

        $this->taskSync->upsert($row);
    }

    public function syncProject(int $bitrixGroupId): void
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
        $this->fetch->importMissingUsers([$this->projectMapper->ownerBitrixId($group), ...array_keys($this->projectMapper->members($members))]);

        $this->projectSync->upsert($group, $members);
    }

    public function syncTaskChat(int $bitrixTaskId): void
    {
        $task = $this->tasks->findByBitrixId($bitrixTaskId);

        if ($task instanceof Task && $task->isInBitrix()) {
            $this->chat->refreshFromBitrix($task);
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
            $this->tasks->delete($task);
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
            $this->projects->delete($project);
            $this->deletions->recordDeleted('groups', [$bitrixGroupId], 'event');
        }
    }
}
