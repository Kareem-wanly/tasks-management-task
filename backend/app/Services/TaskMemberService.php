<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Task\DelegateTaskDTO;
use App\DTOs\Task\TaskMembersDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixFingerprint;
use App\Integrations\Bitrix24\Contracts\BitrixTaskRepositoryInterface;
use App\Models\BitrixOutboundOp;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\BitrixOutboundOpRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\BitrixSyncServiceInterface;
use App\Services\Contracts\TaskMemberServiceInterface;
use Illuminate\Database\ConnectionInterface;

/**
 * Step 4.1: a task's accomplices and auditors, watching and delegating.
 *
 * Tasks in Bitrix change there first, with the employee's token. Bitrix adds
 * people of its own to these lists (a delegator who did not create the task
 * becomes an auditor), so after each change we save the task as Bitrix now
 * has it. Tasks of local-only projects change here only.
 */
final readonly class TaskMemberService implements TaskMemberServiceInterface
{
    public function __construct(
        private TaskRepositoryInterface $tasks,
        private UserRepositoryInterface $users,
        private ConnectionInterface $database,
        private BitrixTaskRepositoryInterface $bitrixTasks,
        private BitrixSyncServiceInterface $bitrixSync,
        private BitrixOutboundOpRepositoryInterface $ops,
        private BitrixFingerprint $fingerprints,
    ) {}

    /** Replaces both lists. */
    public function setMembers(User $actor, Task $task, TaskMembersDTO $members): Task
    {
        if ($task->isInBitrix()) {
            $this->ensureSignedInWithBitrix($actor);
            // An empty list clears it.
            $this->bitrixTasks->setMembers($actor, $task->bitrix_task_id, $this->bitrixIds($members->accompliceIds), $this->bitrixIds($members->auditorIds));
            $this->refreshFromBitrix($actor, $task);
        } else {
            $this->database->transaction(function () use ($task, $members): void {
                $this->tasks->syncAccomplices($task, $members->accompliceIds);
                $this->tasks->syncAuditors($task, $members->auditorIds);
            });
        }

        return $this->tasks->freshForDetail($task, $actor);
    }

    /** The actor starts or stops following the task. */
    public function watch(User $actor, Task $task, bool $watch): Task
    {
        if ($task->isInBitrix()) {
            $this->ensureSignedInWithBitrix($actor);
            $this->bitrixTasks->watch($actor, $task->bitrix_task_id, $watch);
            $this->refreshFromBitrix($actor, $task);
        } else {
            $watch ? $this->tasks->addAuditor($task, $actor->id) : $this->tasks->removeAuditor($task, $actor->id);
        }

        return $this->tasks->freshForDetail($task, $actor);
    }

    /** The responsible person hands the task over and keeps following it. */
    public function delegate(User $actor, Task $task, DelegateTaskDTO $data): Task
    {
        $to = $this->users->findOrFail($data->userId);

        if ($task->isInBitrix()) {
            $this->ensureSignedInWithBitrix($actor);
            // Bitrix makes the delegator an auditor unless they created the task.
            $this->bitrixTasks->delegate($actor, $task->bitrix_task_id, $this->bitrixIds([$to->id])[0]);
            $this->refreshFromBitrix($actor, $task);
        } else {
            $this->database->transaction(function () use ($actor, $task, $to): void {
                $task->assigned_to = $to->id;
                $this->tasks->save($task);
                $this->tasks->addAuditor($task, $actor->id);
            });
        }

        return $this->tasks->freshForDetail($task, $actor);
    }

    private function ensureSignedInWithBitrix(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }

    /**
     * Saves the task as Bitrix now has it, and records the change so the
     * event Bitrix sends for it is recognised as our echo (step 3.5).
     */
    private function refreshFromBitrix(User $actor, Task $task): void
    {
        $this->bitrixSync->refreshTask($task->bitrixId());
        $this->tasks->reload($task);
        $this->ops->record(BitrixOutboundOp::TASK, $task->bitrixId(), 'update', $this->fingerprints->task($task), $actor);
    }

    /**
     * Only Bitrix24 users can be on a Bitrix task.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function bitrixIds(array $userIds): array
    {
        return array_values($this->users->findMany($userIds)
            ->map(fn (User $user): int => $user->bitrix_user_id
                ?? throw BitrixException::cannotSync("{$user->name} is not a Bitrix24 user, so they cannot be added to this task."))
            ->all());
    }
}
