<?php

namespace App\Integrations\Bitrix24;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;

/**
 * A hash of the fields we sync, in Bitrix terms (Bitrix status/priority
 * values, Bitrix user/group IDs), so a record we sent and the same record
 * fetched back from Bitrix hash the same (step 3.5).
 */
class BitrixFingerprint
{
    public static function task(Task $task): string
    {
        $status = (int) TaskStatus::where('key', $task->status)->value('bitrix_id');

        return self::hash([
            $task->title,
            $task->description ?? '',
            // Bitrix 1 (New) behaves as 2 (Pending).
            $status === 1 ? 2 : $status,
            (int) TaskPriority::where('key', $task->priority)->value('bitrix_id'),
            $task->due_date?->utc()->format('Y-m-d H:i'),
            $task->assigned_to ? (int) User::whereKey($task->assigned_to)->value('bitrix_user_id') : 0,
            $task->project_id ? (int) Project::withTrashed()->whereKey($task->project_id)->value('bitrix_group_id') : 0,
        ]);
    }

    public static function project(Project $project): string
    {
        return self::hash([
            $project->title,
            $project->description ?? '',
            $project->start_date?->toDateString(),
            $project->due_date?->toDateString(),
            $project->status === 'archived',
        ]);
    }

    private static function hash(array $values): string
    {
        return hash('sha256', json_encode($values));
    }
}
