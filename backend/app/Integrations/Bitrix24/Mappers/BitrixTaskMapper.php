<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Mappers;

use App\Enums\TaskMemberRole;
use App\Integrations\Bitrix24\BitrixPayload;
use App\Integrations\Bitrix24\Contracts\BitrixTaskMapperInterface;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Turns a Bitrix24 task (tasks.task.list, camelCase keys) into our tasks
 * columns. People and project are returned as Bitrix IDs; BitrixTaskResolver
 * resolves them.
 *
 * @phpstan-import-type TaskAttributes from BitrixTaskMapperInterface
 */
final readonly class BitrixTaskMapper implements BitrixTaskMapperInterface
{
    /** The fields tasks.task.list must select for this mapper. */
    public const SELECT = ['ID', 'TITLE', 'DESCRIPTION', 'STATUS', 'PRIORITY', 'RESPONSIBLE_ID', 'CREATED_BY', 'GROUP_ID', 'DEADLINE', 'CREATED_DATE', 'CLOSED_DATE', 'ACCOMPLICES', 'AUDITORS', 'PARENT_ID', 'ALLOW_TIME_TRACKING', 'TIME_ESTIMATE', 'TIME_SPENT_IN_LOGS'];

    /** Bitrix defaults, used when a value has no row of ours. */
    private const DEFAULT_STATUS = 2;

    private const DEFAULT_PRIORITY = 1;

    public function __construct(private TaskLookupRepositoryInterface $lookups) {}

    /**
     * @param  array<string, mixed>  $task
     * @return TaskAttributes
     */
    public function attributes(array $task): array
    {
        $row = BitrixPayload::of($task, 'tasks.task.list');
        $bitrixId = $row->int('id');
        $title = trim($row->stringOrNull('title') ?? '');
        $description = trim($row->stringOrNull('description') ?? '');

        $attributes = [
            'bitrix_task_id' => $bitrixId,
            'title' => $title !== '' ? $title : "Bitrix task {$bitrixId}",
            // Bitrix keeps descriptions in BBCode; stored as-is for now.
            'description' => $description !== '' ? $description : null,
            'status' => $this->lookups->keyForBitrix(TaskStatus::class, $row->intOrNull('status') ?? 0)
                ?? $this->lookups->keyForBitrix(TaskStatus::class, self::DEFAULT_STATUS),
            'priority' => $this->lookups->keyForBitrix(TaskPriority::class, $row->intOrNull('priority') ?? 0)
                ?? $this->lookups->keyForBitrix(TaskPriority::class, self::DEFAULT_PRIORITY),
            'due_date' => $row->localDateOrNull('deadline'),
            'allow_time_tracking' => ($task['allowTimeTracking'] ?? 'N') === 'Y',
            'time_estimate' => $row->intOrNull('timeEstimate') ?? 0,
            'time_spent' => $row->intOrNull('timeSpentInLogs') ?? 0,
            'completed_at' => $row->localDateOrNull('closedDate'),
        ];

        if (($created = $row->localDateOrNull('createdDate')) instanceof CarbonImmutable) {
            $attributes['created_at'] = $created;
        }

        return $attributes;
    }

    /** @param  array<string, mixed>  $task */
    public function creatorBitrixId(array $task): int
    {
        return BitrixPayload::of($task, 'tasks.task.list')->intOrNull('createdBy') ?? 0;
    }

    /** @param  array<string, mixed>  $task */
    public function responsibleBitrixId(array $task): int
    {
        return BitrixPayload::of($task, 'tasks.task.list')->intOrNull('responsibleId') ?? 0;
    }

    /**
     * Step 4.1: accomplices and auditors as sorted Bitrix user IDs.
     *
     * @param  array<string, mixed>  $task
     * @return array{accomplice: list<int>, auditor: list<int>}|null Null when the row has no member lists (not selected).
     */
    public function members(array $task): ?array
    {
        if (! array_key_exists('accomplices', $task) && ! array_key_exists('auditors', $task)) {
            return null;
        }

        $row = BitrixPayload::of($task, 'tasks.task.list');
        $ids = function (string $field) use ($row): array {
            $ids = array_values(array_unique(array_filter($row->ints($field))));
            sort($ids);

            return $ids;
        };

        return [TaskMemberRole::Accomplice->value => $ids('accomplices'), TaskMemberRole::Auditor->value => $ids('auditors')];
    }

    /**
     * 0 means the task is not a subtask.
     *
     * @param  array<string, mixed>  $task
     */
    public function parentBitrixId(array $task): int
    {
        return BitrixPayload::of($task, 'tasks.task.list')->intOrNull('parentId') ?? 0;
    }

    /**
     * 0 means the task has no project.
     *
     * @param  array<string, mixed>  $task
     */
    public function groupBitrixId(array $task): int
    {
        return BitrixPayload::of($task, 'tasks.task.list')->intOrNull('groupId') ?? 0;
    }
}
