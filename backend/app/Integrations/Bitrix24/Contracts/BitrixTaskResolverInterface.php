<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use Carbon\CarbonImmutable;

/**
 * Our columns for a Bitrix24 task row, people and project resolved to our IDs,
 * without saving anything. Used to save the task, and to compare it with our
 * own change coming back (echo check).
 *
 * @phpstan-type ResolvedTask array{bitrix_task_id: int, title: string, description: string|null, status: string|null, priority: string|null, due_date: CarbonImmutable|null, allow_time_tracking: bool, time_estimate: int, time_spent: int, completed_at: CarbonImmutable|null, created_at?: CarbonImmutable, project_id: int|null, parent_id: int|null, created_by: int, assigned_to: int|null}
 */
interface BitrixTaskResolverInterface
{
    /**
     * @param  array<string, mixed>  $row  A tasks.task.list / tasks.task.get row.
     * @return ResolvedTask|null Null when its creator or project isn't imported.
     */
    public function resolve(array $row): ?array;
}
