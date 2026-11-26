<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Enums\TaskMemberRole;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Creates or updates our copy of a Bitrix24 task. Bitrix wins on conflict.
 * Users and projects must be imported first (steps 2.1 and 2.2).
 */
final readonly class BitrixTaskSync
{
    public function __construct(
        private BitrixTaskMapper $mapper,
        private TaskRepositoryInterface $tasks,
        private ProjectRepositoryInterface $projects,
        private UserRepositoryInterface $users,
        private TaskLookupRepositoryInterface $lookups,
        private BitrixRecordFetcher $fetch,
    ) {}

    /**
     * Saves the task as Bitrix now has it, importing members we don't know
     * yet. Used after our own member changes: Bitrix adds people of its own
     * (a delegator who did not create the task becomes an auditor).
     */
    public function refresh(int $bitrixTaskId): void
    {
        $row = $this->fetch->task($bitrixTaskId)
            ?? throw BitrixException::cannotSync('The task is no longer in Bitrix24.');

        $members = $this->mapper->members($row);
        $this->fetch->importMissingUsers([...$members[TaskMemberRole::Accomplice->value] ?? [], ...$members[TaskMemberRole::Auditor->value] ?? []]);
        $this->upsert($row);
    }

    /**
     * @return Task|null Null when its creator or project isn't imported.
     */
    public function upsert(array $row): ?Task
    {
        $attributes = $this->resolve($row);
        if ($attributes === null) {
            return null;
        }

        $task = $this->tasks->findByBitrixId($attributes['bitrix_task_id'], withTrashed: true) ?? new Task;

        if ($task->trashed()) {
            $this->tasks->restore($task);
        }

        $task->forceFill($this->keepCustomLookups($task, $attributes));

        // Save only real changes, so TaskObserver doesn't log an "updated"
        // entry on every import.
        if (! $task->exists || $task->isDirty()) {
            $this->tasks->save($task);
        }

        if (($members = $this->mapper->members($row)) !== null) {
            $this->syncMembers($task, $members);
        }

        // Bookkeeping, not a change: updated_at keeps the last real change.
        $this->tasks->markSynced($task);

        return $task;
    }

    /**
     * Our columns for a Bitrix task, people and project resolved to our IDs,
     * without saving anything.
     *
     * @return array<string, mixed>|null Null when its creator or project isn't imported.
     */
    public function resolve(array $row): ?array
    {
        $attributes = $this->mapper->attributes($row);
        $context = ['bitrix_task_id' => $attributes['bitrix_task_id']];

        $creatorId = $this->users->idByBitrixId($this->mapper->creatorBitrixId($row));
        if ($creatorId === null) {
            Log::warning('Bitrix24 task skipped: its creator is not imported.', $context);

            return null;
        }

        $groupId = $this->mapper->groupBitrixId($row);
        $projectId = $groupId === 0 ? null : $this->projects->idByBitrixGroupId($groupId);
        if ($groupId !== 0 && $projectId === null) {
            Log::warning('Bitrix24 task skipped: its project is not imported.', $context + ['bitrix_group_id' => $groupId]);

            return null;
        }

        $responsibleBitrixId = $this->mapper->responsibleBitrixId($row);
        $assigneeId = $this->users->idByBitrixId($responsibleBitrixId);
        if ($responsibleBitrixId !== 0 && $assigneeId === null) {
            Log::warning('Bitrix24 task imported without assignee: responsible user is not imported.', $context);
        }

        return [
            ...$attributes,
            'project_id' => $projectId,
            'parent_id' => $this->parentId($row, $context),
            'created_by' => $creatorId,
            'assigned_to' => $assigneeId,
        ];
    }

    /**
     * A custom status/priority of ours sends the nearest Bitrix value (urgent
     * goes as "high"). While Bitrix still has that value, keep our key, or
     * every sync would turn urgent back into high.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function keepCustomLookups(Task $task, array $attributes): array
    {
        if (! $task->exists) {
            return $attributes;
        }

        foreach (['status' => TaskStatus::class, 'priority' => TaskPriority::class] as $column => $lookup) {
            $current = $task->getRawOriginal($column);
            if ($current !== null && $current !== $attributes[$column]
                && $this->lookups->bitrixIdForKey($lookup, $current) === $this->lookups->bitrixIdForKey($lookup, $attributes[$column])) {
                $attributes[$column] = $current;
            }
        }

        return $attributes;
    }

    /**
     * Step 4.1: our accomplices and auditors become Bitrix's. People not
     * imported yet are left out (the event job imports them first).
     *
     * @param  array{accomplice: list<int>, auditor: list<int>}  $members  Bitrix user IDs
     */
    private function syncMembers(Task $task, array $members): void
    {
        foreach (TaskMemberRole::cases() as $role) {
            $userIds = array_values($this->users->idsByBitrixIds($members[$role->value]));

            if (count($userIds) < count($members[$role->value])) {
                Log::warning("Bitrix24 task {$role->value}s skipped: not imported.", ['bitrix_task_id' => $task->bitrix_task_id]);
            }

            $role === TaskMemberRole::Accomplice ? $this->tasks->syncAccomplices($task, $userIds) : $this->tasks->syncAuditors($task, $userIds);
        }
    }

    /**
     * Step 4.2. A parent we don't have yet (imported later in the same run)
     * is left empty; the next sync of this task links it.
     */
    private function parentId(array $row, array $context): ?int
    {
        $bitrixId = $this->mapper->parentBitrixId($row);
        if ($bitrixId === 0) {
            return null;
        }

        $parentId = $this->tasks->idByBitrixId($bitrixId);
        if ($parentId === null) {
            Log::warning('Bitrix24 subtask imported without its parent: the parent is not imported yet.', $context + ['bitrix_parent_id' => $bitrixId]);
        }

        return $parentId;
    }
}
