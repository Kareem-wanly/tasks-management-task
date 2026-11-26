<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use Carbon\CarbonImmutable;

/**
 * Turns a Bitrix24 task (tasks.task.list, camelCase keys) into our tasks
 * columns. People and project come back as Bitrix IDs; the resolver turns
 * them into ours.
 *
 * @phpstan-type TaskAttributes array{bitrix_task_id: int, title: string, description: string|null, status: string|null, priority: string|null, due_date: CarbonImmutable|null, allow_time_tracking: bool, time_estimate: int, time_spent: int, completed_at: CarbonImmutable|null, created_at?: CarbonImmutable}
 */
interface BitrixTaskMapperInterface
{
    /**
     * @param  array<string, mixed>  $task
     * @return TaskAttributes
     */
    public function attributes(array $task): array;

    /** @param  array<string, mixed>  $task */
    public function creatorBitrixId(array $task): int;

    /** @param  array<string, mixed>  $task */
    public function responsibleBitrixId(array $task): int;

    /**
     * Step 4.1: accomplices and auditors as sorted Bitrix user IDs.
     *
     * @param  array<string, mixed>  $task
     * @return array{accomplice: list<int>, auditor: list<int>}|null Null when the row has no member lists (not selected).
     */
    public function members(array $task): ?array;

    /**
     * 0 means the task is not a subtask.
     *
     * @param  array<string, mixed>  $task
     */
    public function parentBitrixId(array $task): int;

    /**
     * 0 means the task has no project.
     *
     * @param  array<string, mixed>  $task
     */
    public function groupBitrixId(array $task): int;
}
