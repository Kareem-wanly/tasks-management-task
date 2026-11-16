<?php

namespace App\Integrations\Bitrix24;

use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Creates or updates our copy of a Bitrix24 task. Bitrix wins on conflict.
 * Users and projects must be imported first (steps 2.1 and 2.2).
 */
class BitrixTaskSync
{
    public function __construct(private readonly BitrixTaskMapper $mapper) {}

    /**
     * @return Task|null Null when its creator or project isn't imported.
     */
    public function upsert(array $row): ?Task
    {
        $attributes = $this->resolve($row);
        if ($attributes === null) {
            return null;
        }

        $task = Task::withTrashed()->where('bitrix_task_id', $attributes['bitrix_task_id'])->first() ?? new Task;

        if ($task->trashed()) {
            $task->restore();
        }

        $task->forceFill($this->keepCustomLookups($task, $attributes));

        // Save only real changes, so TaskObserver doesn't log an "updated"
        // entry on every import.
        if (! $task->exists || $task->isDirty()) {
            $task->save();
        }

        if (($members = $this->mapper->members($row)) !== null) {
            $this->syncMembers($task, $members);
        }

        // Bookkeeping, not a change: updated_at keeps the last real change.
        Task::withoutTimestamps(fn () => $task->forceFill(['bitrix_synced_at' => now()])->saveQuietly());

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

        $creatorId = $this->userId($this->mapper->creatorBitrixId($row));
        if ($creatorId === null) {
            Log::warning('Bitrix24 task skipped: its creator is not imported.', $context);

            return null;
        }

        $groupId = $this->mapper->groupBitrixId($row);
        $projectId = $groupId === 0 ? null : Project::where('bitrix_group_id', $groupId)->value('id');
        if ($groupId !== 0 && $projectId === null) {
            Log::warning('Bitrix24 task skipped: its project is not imported.', $context + ['bitrix_group_id' => $groupId]);

            return null;
        }

        $responsibleBitrixId = $this->mapper->responsibleBitrixId($row);
        $assigneeId = $this->userId($responsibleBitrixId);
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
                && $lookup::where('key', $current)->value('bitrix_id') === $lookup::where('key', $attributes[$column])->value('bitrix_id')) {
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
        foreach ([Task::ACCOMPLICE => $task->accomplices(), Task::AUDITOR => $task->auditors()] as $role => $relation) {
            $userIds = User::whereIn('bitrix_user_id', $members[$role])->pluck('id')->all();

            if (count($userIds) < count($members[$role])) {
                Log::warning("Bitrix24 task {$role}s skipped: not imported.", ['bitrix_task_id' => $task->bitrix_task_id]);
            }

            $relation->sync($userIds);
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

        $parentId = Task::where('bitrix_task_id', $bitrixId)->value('id');
        if ($parentId === null) {
            Log::warning('Bitrix24 subtask imported without its parent: the parent is not imported yet.', $context + ['bitrix_parent_id' => $bitrixId]);
        }

        return $parentId;
    }

    private function userId(int $bitrixId): ?int
    {
        return $bitrixId === 0 ? null : User::where('bitrix_user_id', $bitrixId)->value('id');
    }
}
