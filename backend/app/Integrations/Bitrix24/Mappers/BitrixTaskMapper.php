<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Mappers;

use App\Enums\TaskMemberRole;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Turns a Bitrix24 task (tasks.task.list, camelCase keys) into our tasks
 * columns. People and project are returned as Bitrix IDs; BitrixTaskSync
 * resolves them.
 */
final readonly class BitrixTaskMapper
{
    /** The fields tasks.task.list must select for this mapper. */
    public const SELECT = ['ID', 'TITLE', 'DESCRIPTION', 'STATUS', 'PRIORITY', 'RESPONSIBLE_ID', 'CREATED_BY', 'GROUP_ID', 'DEADLINE', 'CREATED_DATE', 'CLOSED_DATE', 'ACCOMPLICES', 'AUDITORS', 'PARENT_ID', 'ALLOW_TIME_TRACKING', 'TIME_ESTIMATE', 'TIME_SPENT_IN_LOGS'];

    /** Bitrix defaults, used when a value has no row of ours. */
    private const DEFAULT_STATUS = 2;

    private const DEFAULT_PRIORITY = 1;

    public function __construct(private TaskLookupRepositoryInterface $lookups) {}

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
            'status' => $this->lookups->keyForBitrix(TaskStatus::class, (int) ($task['status'] ?? 0))
                ?? $this->lookups->keyForBitrix(TaskStatus::class, self::DEFAULT_STATUS),
            'priority' => $this->lookups->keyForBitrix(TaskPriority::class, (int) ($task['priority'] ?? 0))
                ?? $this->lookups->keyForBitrix(TaskPriority::class, self::DEFAULT_PRIORITY),
            'due_date' => $this->dateTime($task['deadline'] ?? null),
            'allow_time_tracking' => ($task['allowTimeTracking'] ?? 'N') === 'Y',
            'time_estimate' => (int) ($task['timeEstimate'] ?? 0),
            'time_spent' => (int) ($task['timeSpentInLogs'] ?? 0),
            'completed_at' => $this->dateTime($task['closedDate'] ?? null),
        ];

        if (($created = $this->dateTime($task['createdDate'] ?? null)) instanceof CarbonImmutable) {
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

    /**
     * Step 4.1: accomplices and auditors as sorted Bitrix user IDs.
     *
     * @return array{accomplice: list<int>, auditor: list<int>}|null Null when the row has no member lists (not selected).
     */
    public function members(array $task): ?array
    {
        if (! array_key_exists('accomplices', $task) && ! array_key_exists('auditors', $task)) {
            return null;
        }

        $ids = function (mixed $list): array {
            $ids = array_values(array_unique(array_filter(array_map('intval', is_array($list) ? $list : []))));
            sort($ids);

            return $ids;
        };

        return [TaskMemberRole::Accomplice->value => $ids($task['accomplices'] ?? []), TaskMemberRole::Auditor->value => $ids($task['auditors'] ?? [])];
    }

    /** 0 means the task is not a subtask. */
    public function parentBitrixId(array $task): int
    {
        return (int) ($task['parentId'] ?? 0);
    }

    /** 0 means the task has no project. */
    public function groupBitrixId(array $task): int
    {
        return (int) ($task['groupId'] ?? 0);
    }

    private function dateTime(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== ''
            ? \Illuminate\Support\Facades\Date::parse($value)->setTimezone(config('app.timezone'))
            : null;
    }
}
