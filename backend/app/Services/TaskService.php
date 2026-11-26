<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Task\ChangeTaskStatusDTO;
use App\DTOs\Task\CreateTaskDTO;
use App\DTOs\Task\TaskFiltersDTO;
use App\DTOs\Task\UpdateTaskDTO;
use App\Enums\SystemRole;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixFingerprint;
use App\Integrations\Bitrix24\Contracts\BitrixTaskRepositoryInterface;
use App\Models\BitrixOutboundOp;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Repositories\Contracts\BitrixOutboundOpRepositoryInterface;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\TaskServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use LogicException;

/**
 * Tasks. Bitrix-synced writes go to Bitrix first (with the employee's
 * token) and are saved here only when Bitrix accepts them; a refusal throws
 * a BitrixException, rendered in bootstrap/app.php.
 */
final readonly class TaskService implements TaskServiceInterface
{
    public function __construct(
        private TaskRepositoryInterface $tasks,
        private UserRepositoryInterface $users,
        private TaskLookupRepositoryInterface $lookups,
        private BitrixTaskRepositoryInterface $bitrixTasks,
        private BitrixOutboundOpRepositoryInterface $ops,
        private BitrixFingerprint $fingerprints,
    ) {}

    /** @return LengthAwarePaginator<int, Task> */
    public function list(User $viewer, TaskFiltersDTO $filters, int $perPage): LengthAwarePaginator
    {
        // Administrators see every task; everyone else what they may see.
        return $this->tasks->paginate($viewer->hasRole(SystemRole::Administrator) ? null : $viewer, $filters, $perPage);
    }

    /** @return LengthAwarePaginator<int, Task> */
    public function listForProject(Project $project, int $perPage): LengthAwarePaginator
    {
        return $this->tasks->paginateForProject($project, $perPage);
    }

    public function show(User $viewer, Task $task): Task
    {
        return $this->tasks->loadForDetail($task, $viewer);
    }

    /**
     * A task in a project, or (no project) a Bitrix personal task (GROUP_ID 0).
     */
    public function create(User $actor, ?Project $project, CreateTaskDTO $data): Task
    {
        $task = new Task($data->toArray());
        $task->project_id = $project?->id;
        $task->created_by = $actor->id;
        // Step 4.4: tasks made here track time from the start.
        $task->allow_time_tracking ??= true;

        $this->createInBitrix($actor, $task);
        $this->tasks->save($task);

        return $this->tasks->loadForDetail($task, $actor);
    }

    public function update(User $actor, Task $task, UpdateTaskDTO $data): Task
    {
        $task->fill($data->toArray());
        $this->updateInBitrix($actor, $task);
        $this->tasks->save($task);

        return $this->tasks->freshForDetail($task, $actor);
    }

    public function changeStatus(User $actor, Task $task, ChangeTaskStatusDTO $data): Task
    {
        $task->status = $data->status;
        $this->updateInBitrix($actor, $task);
        $this->tasks->save($task);

        return $this->tasks->freshForDetail($task, $actor);
    }

    public function delete(User $actor, Task $task): void
    {
        $this->deleteInBitrix($actor, $task);
        $this->tasks->delete($task);
    }

    /**
     * Creates the (unsaved) task in Bitrix and keeps what Bitrix decided: its
     * ID and its status.
     */
    private function createInBitrix(User $actor, Task $task): void
    {
        if (! $task->syncsWithBitrix()) {
            return;
        }

        $this->ensureSignedInWithBitrix($actor);

        // A Bitrix task always has a responsible person: default to the creator.
        $task->assigned_to ??= $actor->id;
        // Values the request left out get Bitrix's defaults (Pending, Normal).
        $task->status ??= $this->lookups->keyForBitrix(TaskStatus::class, BitrixTaskRepositoryInterface::STATUS_PENDING)
            ?? throw new LogicException('No task status is linked to the Bitrix24 "pending" status.');
        $task->priority ??= $this->lookups->keyForBitrix(TaskPriority::class, BitrixTaskRepositoryInterface::DEFAULT_PRIORITY)
            ?? throw new LogicException('No task priority is linked to the Bitrix24 default priority.');

        $values = $this->bitrixValues($task, $this->bitrixTasks->columns());
        $created = $this->bitrixTasks->create($actor, $values, $task->project->bitrix_group_id ?? 0);
        $task->bitrix_task_id = $created['id'];

        $this->applyStatusInBitrix($actor, $task, $created['status']);
        $task->bitrix_synced_at = now();
        $this->recordOutbound($actor, $task, 'create');
    }

    /** Sends the task's unsaved changes (its dirty attributes). */
    private function updateInBitrix(User $actor, Task $task): void
    {
        if (! $task->isInBitrix()) {
            return;
        }

        $this->ensureSignedInWithBitrix($actor);

        $changed = $this->bitrixTasks->syncedColumns(array_keys($task->getDirty()));
        if ($changed !== []) {
            $this->bitrixTasks->update($actor, $task->bitrix_task_id, $this->bitrixValues($task, $changed));
        }

        if ($task->isDirty('status')) {
            $original = $task->getOriginal('status');
            $this->applyStatusInBitrix($actor, $task, $this->bitrixStatus(is_string($original) ? $original : null));
        }

        if ($changed !== [] || $task->isDirty('status')) {
            $task->bitrix_synced_at = now();
            $this->recordOutbound($actor, $task, 'update');
        }
    }

    private function deleteInBitrix(User $actor, Task $task): void
    {
        if (! $task->isInBitrix()) {
            return;
        }

        $this->ensureSignedInWithBitrix($actor);
        $this->bitrixTasks->delete($actor, $task->bitrix_task_id);
        $this->ops->record(BitrixOutboundOp::TASK, $task->bitrix_task_id, 'delete', null, $actor);
    }

    /**
     * Bitrix's final status is what we keep, except that a custom status of
     * ours sending the same Bitrix value stays as chosen.
     */
    private function applyStatusInBitrix(User $actor, Task $task, int $current): void
    {
        $target = $this->bitrixStatus($task->status);
        $final = $this->bitrixTasks->changeStatus($actor, $task->bitrixId(), $current, $target);

        if ($this->bitrixTasks->normalizeStatus($final) !== $target) {
            $task->status = $this->lookups->keyForBitrix(TaskStatus::class, $final) ?? $task->status;
        }
    }

    private function bitrixStatus(?string $key): int
    {
        $bitrixId = $this->lookups->bitrixIdForKey(TaskStatus::class, $key)
            ?? throw BitrixException::cannotSync("Status \"{$key}\" has no Bitrix24 value.");

        return $this->bitrixTasks->normalizeStatus($bitrixId);
    }

    /**
     * The given columns with every link turned into its Bitrix ID. Each one
     * that can't be expressed in Bitrix is refused, before anything is sent.
     *
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function bitrixValues(Task $task, array $columns): array
    {
        $values = [];

        foreach ($columns as $column) {
            $values[$column] = match ($column) {
                'priority' => $this->lookups->bitrixIdForKey(TaskPriority::class, $task->priority)
                    ?? throw BitrixException::cannotSync("Priority \"{$task->priority}\" has no Bitrix24 value."),
                'assigned_to' => $this->responsibleBitrixId($task->assigned_to),
                // 0 detaches it from its parent.
                'parent_id' => $this->parentBitrixId($task->parent_id),
                default => $task->{$column},
            };
        }

        return $values;
    }

    /** A subtask in Bitrix needs its parent there too. */
    private function parentBitrixId(?int $parentId): int
    {
        if ($parentId === null) {
            return 0;
        }

        return $this->tasks->bitrixTaskIdOf($parentId)
            ?? throw BitrixException::cannotSync('The parent task is not in Bitrix24, so this task cannot be its subtask there.');
    }

    /** A Bitrix task always has a responsible person, and they must be a Bitrix user. */
    private function responsibleBitrixId(?int $userId): int
    {
        if ($userId === null) {
            throw BitrixException::cannotSync('A Bitrix24 task always has a responsible person; choose an assignee.');
        }

        $user = $this->users->findMany([$userId])->first();

        return $user->bitrix_user_id
            ?? throw BitrixException::cannotSync(($user->name ?? 'This user').' is not a Bitrix24 user, so the task cannot be assigned to them.');
    }

    /**
     * Checked first, so an employee who never signed in with Bitrix gets
     * that reason rather than an error about the fields they sent.
     */
    private function ensureSignedInWithBitrix(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }

    /** Remembered so the echo of our change is recognised, not taken for a change made in Bitrix. */
    private function recordOutbound(User $actor, Task $task, string $action): void
    {
        $this->ops->record(BitrixOutboundOp::TASK, (int) $task->bitrix_task_id, $action, $this->fingerprints->task($task), $actor);
    }
}
