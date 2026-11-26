<?php

declare(strict_types=1);

namespace App\Actions\Bitrix;

use App\Enums\TaskMemberRole;
use App\Integrations\Bitrix24\Contracts\BitrixTaskMapperInterface;
use App\Integrations\Bitrix24\Contracts\BitrixTaskResolverInterface;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Creates or updates our copy of a Bitrix24 task. Bitrix wins on conflict.
 *
 * @phpstan-import-type ResolvedTask from BitrixTaskResolverInterface
 */
final readonly class SyncBitrixTaskAction
{
    public function __construct(
        private BitrixTaskResolverInterface $resolver,
        private BitrixTaskMapperInterface $mapper,
        private TaskRepositoryInterface $tasks,
        private UserRepositoryInterface $users,
        private TaskLookupRepositoryInterface $lookups,
    ) {}

    /**
     * @param  array<string, mixed>  $row  A tasks.task.list / tasks.task.get row.
     * @return Task|null Null when its creator or project isn't imported.
     */
    public function handle(array $row): ?Task
    {
        $attributes = $this->resolver->resolve($row);
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
     * A custom status/priority of ours sends the nearest Bitrix value (urgent
     * goes as "high"). While Bitrix still has that value, keep our key, or
     * every sync would turn urgent back into high.
     *
     * @param  ResolvedTask  $attributes
     * @return ResolvedTask
     */
    private function keepCustomLookups(Task $task, array $attributes): array
    {
        if (! $task->exists) {
            return $attributes;
        }

        foreach (['status' => TaskStatus::class, 'priority' => TaskPriority::class] as $column => $lookup) {
            // The stored key (a text column): null when the task has none.
            $current = $task->getRawOriginal($column);
            if (is_string($current) && $current !== $attributes[$column]
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
}
