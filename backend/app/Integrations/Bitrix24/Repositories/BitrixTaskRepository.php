<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTaskRepositoryInterface;
use App\Models\User;
use Carbon\CarbonInterface;

final readonly class BitrixTaskRepository implements BitrixTaskRepositoryInterface
{
    /** Bitrix STATUS values. 1 (New) is legacy and behaves like Pending. */
    private const NEW = 1;

    private const IN_PROGRESS = 3;

    private const AWAITING_CONTROL = 4;

    private const COMPLETED = 5;

    private const DEFERRED = 6;

    private const DECLINED = 7;

    /** At most this many status actions per change. */
    private const MAX_STATUS_STEPS = 3;

    /** Our columns => Bitrix fields that tasks.task.add/update accept. */
    private const FIELDS = [
        'title' => 'TITLE',
        'description' => 'DESCRIPTION',
        'priority' => 'PRIORITY',
        'due_date' => 'DEADLINE',
        'assigned_to' => 'RESPONSIBLE_ID',
        'parent_id' => 'PARENT_ID',
        'allow_time_tracking' => 'ALLOW_TIME_TRACKING',
        'time_estimate' => 'TIME_ESTIMATE',
    ];

    public function __construct(private BitrixGatewayInterface $bitrix) {}

    public function columns(): array
    {
        return array_keys(self::FIELDS);
    }

    public function syncedColumns(array $columns): array
    {
        return array_values(array_intersect($columns, array_keys(self::FIELDS)));
    }

    public function create(User $actor, array $values, int $groupId): array
    {
        $fields = [...$this->fields($values), 'GROUP_ID' => $groupId];

        $created = $this->bitrix->callAsUser($actor, 'tasks.task.add', ['fields' => $fields])['result']['task'] ?? [];
        $id = (int) ($created['id'] ?? 0) ?: throw BitrixException::cannotSync('Bitrix24 did not return the new task.');

        return ['id' => $id, 'status' => (int) ($created['status'] ?? self::STATUS_PENDING)];
    }

    public function update(User $actor, int $bitrixTaskId, array $values): void
    {
        $this->bitrix->callAsUser($actor, 'tasks.task.update', ['taskId' => $bitrixTaskId, 'fields' => $this->fields($values)]);
    }

    public function delete(User $actor, int $bitrixTaskId): void
    {
        $this->bitrix->callAsUser($actor, 'tasks.task.delete', ['taskId' => $bitrixTaskId]);
    }

    /**
     * Each action returns the task, so we walk from the status Bitrix reports
     * until we reach the target or Bitrix stops us (e.g. completing a task that
     * needs the creator's approval ends in "awaiting control").
     */
    public function changeStatus(User $actor, int $bitrixTaskId, int $current, int $target): int
    {
        for ($step = 0; $step < self::MAX_STATUS_STEPS && ($action = $this->nextAction($current, $target)); $step++) {
            $result = $this->bitrix->callAsUser($actor, "tasks.task.{$action}", ['taskId' => $bitrixTaskId]);
            $current = (int) ($result['result']['task']['status'] ?? $current);

            // Completing landed in the creator's review: never approve it in the same go.
            if ($current === self::AWAITING_CONTROL) {
                break;
            }
        }

        return $current;
    }

    public function normalizeStatus(int $status): int
    {
        return $status === self::NEW ? self::STATUS_PENDING : $status;
    }

    public function setMembers(User $actor, int $bitrixTaskId, array $accompliceIds, array $auditorIds): void
    {
        $this->bitrix->callAsUser($actor, 'tasks.task.update', [
            'taskId' => $bitrixTaskId,
            'fields' => ['ACCOMPLICES' => $accompliceIds, 'AUDITORS' => $auditorIds],
        ]);
    }

    public function watch(User $actor, int $bitrixTaskId, bool $watch): void
    {
        $this->bitrix->callAsUser($actor, $watch ? 'tasks.task.startwatch' : 'tasks.task.stopwatch', ['taskId' => $bitrixTaskId]);
    }

    public function delegate(User $actor, int $bitrixTaskId, int $toBitrixUserId): void
    {
        $this->bitrix->callAsUser($actor, 'tasks.task.delegate', ['taskId' => $bitrixTaskId, 'userId' => $toBitrixUserId]);
    }

    public function history(int $bitrixTaskId): array
    {
        $rows = $this->bitrix->callAsService('tasks.task.history.list', ['taskId' => $bitrixTaskId])['result']['list'] ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }

    private function nextAction(int $current, int $target): ?string
    {
        $current = $this->normalizeStatus($current);

        if ($current === $target) {
            return null;
        }

        return match (true) {
            $current === self::AWAITING_CONTROL => $target === self::COMPLETED ? 'approve' : 'disapprove',
            in_array($current, [self::COMPLETED, self::DECLINED], true) => 'renew',
            $target === self::STATUS_PENDING => $current === self::IN_PROGRESS ? 'pause' : 'renew',
            $target === self::IN_PROGRESS => 'start',
            in_array($target, [self::AWAITING_CONTROL, self::COMPLETED], true) => 'complete',
            $target === self::DEFERRED => 'defer',
            default => null,
        };
    }

    /**
     * Our values as Bitrix fields, in the order given.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function fields(array $values): array
    {
        $fields = [];

        foreach ($values as $column => $value) {
            $fields[self::FIELDS[$column]] = match ($column) {
                'description' => $value ?? '',
                // An empty value clears the deadline.
                'due_date' => $value instanceof CarbonInterface ? $value->toIso8601String() : '',
                'allow_time_tracking' => $value ? 'Y' : 'N',
                'time_estimate' => (int) $value,
                default => $value,
            };
        }

        return $fields;
    }
}
