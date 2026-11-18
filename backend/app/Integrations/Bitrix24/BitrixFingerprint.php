<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * A hash of the fields we sync, in Bitrix terms (Bitrix status/priority
 * values, Bitrix user/group/task IDs), so a record we sent and the same
 * record fetched back from Bitrix hash the same (step 3.5).
 */
class BitrixFingerprint
{
    public function __construct(
        private readonly TaskLookupRepositoryInterface $lookups,
        private readonly UserRepositoryInterface $users,
        private readonly ProjectRepositoryInterface $projects,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    /**
     * @param  array{accomplice: list<int>, auditor: list<int>}|null  $members  Sorted Bitrix user IDs; null reads the task's own.
     */
    public function task(Task $task, ?array $members = null): string
    {
        $status = (int) $this->lookups->bitrixIdForKey(TaskStatus::class, $task->status);
        $members ??= $this->tasks->memberBitrixIds($task);

        return $this->hash([
            $task->title,
            $task->description ?? '',
            // Bitrix 1 (New) behaves as 2 (Pending).
            TaskStatus::normalizeBitrixId($status),
            (int) $this->lookups->bitrixIdForKey(TaskPriority::class, $task->priority),
            $task->due_date?->utc()->format('Y-m-d H:i'),
            $task->assigned_to ? (int) $this->users->bitrixIdOf($task->assigned_to) : 0,
            $task->project_id ? (int) $this->projects->bitrixGroupIdOf($task->project_id) : 0,
            $task->parent_id ? (int) $this->tasks->bitrixTaskIdOf($task->parent_id) : 0,
            (bool) $task->allow_time_tracking,
            (int) $task->time_estimate,
            $members['accomplice'],
            $members['auditor'],
        ]);
    }

    public function project(Project $project): string
    {
        return $this->hash([
            $project->title,
            $project->description ?? '',
            $project->start_date?->toDateString(),
            $project->due_date?->toDateString(),
            $project->status === 'archived',
        ]);
    }

    private function hash(array $values): string
    {
        return hash('sha256', json_encode($values));
    }
}
