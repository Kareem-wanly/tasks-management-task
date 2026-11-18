<?php

namespace App\Integrations\Bitrix24;

use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Models\Project;
use App\Models\Task;
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

        $task = Task::withTrashed()->where('bitrix_task_id', $attributes['bitrix_task_id'])->first() ?? new Task;

        if ($task->trashed()) {
            $task->restore();
        }

        $task->forceFill([
            ...$attributes,
            'project_id' => $projectId,
            'created_by' => $creatorId,
            'assigned_to' => $assigneeId,
        ]);

        // Save only real changes, so TaskObserver doesn't log an "updated"
        // entry on every import.
        if (! $task->exists || $task->isDirty()) {
            $task->save();
        }

        $task->forceFill(['bitrix_synced_at' => now()])->saveQuietly();

        return $task;
    }

    private function userId(int $bitrixId): ?int
    {
        return $bitrixId === 0 ? null : User::where('bitrix_user_id', $bitrixId)->value('id');
    }
}
