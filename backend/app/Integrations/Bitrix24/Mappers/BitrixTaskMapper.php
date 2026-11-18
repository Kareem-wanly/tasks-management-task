<?php

namespace App\Integrations\Bitrix24\Mappers;

use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Carbon\Carbon;

/**
 * Turns a Bitrix24 task (tasks.task.list, camelCase keys) into our tasks
 * columns. People and project are returned as Bitrix IDs; BitrixTaskSync
 * resolves them.
 */
class BitrixTaskMapper
{
    /** The fields tasks.task.list must select for this mapper. */
    public const SELECT = ['ID', 'TITLE', 'DESCRIPTION', 'STATUS', 'PRIORITY', 'RESPONSIBLE_ID', 'CREATED_BY', 'GROUP_ID', 'DEADLINE', 'CREATED_DATE', 'CLOSED_DATE'];

    /** Bitrix defaults, used when a value has no row of ours. */
    private const DEFAULT_STATUS = 2;

    private const DEFAULT_PRIORITY = 1;

    /**
     * @return array<string, mixed>
     */
    public function attributes(array $task): array
    {
        $title = trim((string) ($task['title'] ?? ''));
        $description = trim((string) ($task['description'] ?? ''));

        $attributes = [
            'bitrix_task_id' => (int) $task['id'],
            'title' => $title !== '' ? $title : "Bitrix task {$task['id']}",
            // Bitrix keeps descriptions in BBCode; stored as-is for now.
            'description' => $description !== '' ? $description : null,
            'status' => TaskStatus::keyForBitrix((int) ($task['status'] ?? 0))
                ?? TaskStatus::keyForBitrix(self::DEFAULT_STATUS),
            'priority' => TaskPriority::keyForBitrix((int) ($task['priority'] ?? 0))
                ?? TaskPriority::keyForBitrix(self::DEFAULT_PRIORITY),
            'due_date' => $this->dateTime($task['deadline'] ?? null),
            'completed_at' => $this->dateTime($task['closedDate'] ?? null),
        ];

        if ($created = $this->dateTime($task['createdDate'] ?? null)) {
            $attributes['created_at'] = $created;
        }

        return $attributes;
    }

    public function creatorBitrixId(array $task): int
    {
        return (int) ($task['createdBy'] ?? 0);
    }

    public function responsibleBitrixId(array $task): int
    {
        return (int) ($task['responsibleId'] ?? 0);
    }

    /** 0 means the task has no project. */
    public function groupBitrixId(array $task): int
    {
        return (int) ($task['groupId'] ?? 0);
    }

    private function dateTime(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== ''
            ? Carbon::parse($value)->setTimezone(config('app.timezone'))
            : null;
    }
}
