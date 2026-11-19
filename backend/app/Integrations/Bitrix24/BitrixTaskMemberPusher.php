<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Enums\TaskMemberRole;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Mappers\BitrixTaskMapper;
use App\Models\BitrixOutboundOp;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\BitrixOutboundOpRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Step 4.1: accomplices, auditors, watching and delegating, sent with the
 * acting employee's token. Bitrix adds people of its own to these lists
 * (a delegator who did not create the task becomes an auditor), so after
 * each call we fetch the task back and save what Bitrix made of it. Only for
 * tasks it handles(); TaskMemberService changes the others here.
 */
class BitrixTaskMemberPusher
{
    public function __construct(
        private readonly BitrixGatewayInterface $bitrix,
        private readonly BitrixTaskPusher $tasks,
        private readonly BitrixRecordFetcher $fetch,
        private readonly BitrixTaskSync $sync,
        private readonly BitrixTaskMapper $mapper,
        private readonly BitrixOutboundOpRepositoryInterface $ops,
        private readonly BitrixFingerprint $fingerprints,
        private readonly UserRepositoryInterface $users,
        private readonly TaskRepositoryInterface $taskRepository,
    ) {}

    /**
     * Replaces both lists.
     *
     * @param  list<int>  $accompliceIds  Our user IDs
     * @param  list<int>  $auditorIds  Our user IDs
     */
    public function setMembers(User $actor, Task $task, array $accompliceIds, array $auditorIds): void
    {
        $this->ensureLinked($actor);
        $this->bitrix->callAsUser($actor, 'tasks.task.update', [
            'taskId' => $task->bitrix_task_id,
            // An empty list clears it.
            'fields' => ['ACCOMPLICES' => $this->bitrixIds($accompliceIds), 'AUDITORS' => $this->bitrixIds($auditorIds)],
        ]);
        $this->refresh($actor, $task);
    }

    /** The actor starts or stops following the task (becomes or stops being an auditor). */
    public function watch(User $actor, Task $task, bool $watch): void
    {
        $this->ensureLinked($actor);
        $this->bitrix->callAsUser($actor, $watch ? 'tasks.task.startwatch' : 'tasks.task.stopwatch', ['taskId' => $task->bitrix_task_id]);
        $this->refresh($actor, $task);
    }

    /** The responsible person hands the task over; Bitrix makes them an auditor unless they created it. */
    public function delegate(User $actor, Task $task, User $to): void
    {
        $this->ensureLinked($actor);
        $this->bitrix->callAsUser($actor, 'tasks.task.delegate', [
            'taskId' => $task->bitrix_task_id,
            'userId' => $this->bitrixIds([$to->id])[0],
        ]);
        $this->refresh($actor, $task);
    }

    /** Whether the task lives in Bitrix; the others (local-only projects) change here only. */
    public function handles(Task $task): bool
    {
        return $task->bitrix_task_id !== null && $this->tasks->syncs($task);
    }

    private function ensureLinked(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }

    /**
     * Saves the task as Bitrix now has it and records the change, so the
     * event Bitrix sends for it is recognised as our echo (step 3.5).
     */
    private function refresh(User $actor, Task $task): void
    {
        $row = $this->fetch->task($task->bitrix_task_id)
            ?? throw BitrixException::cannotSync('The task is no longer in Bitrix24.');

        $members = $this->mapper->members($row);
        $this->fetch->importMissingUsers([...$members[TaskMemberRole::Accomplice->value] ?? [], ...$members[TaskMemberRole::Auditor->value] ?? []]);
        $this->sync->upsert($row);

        $this->taskRepository->reload($task);
        $this->ops->record(BitrixOutboundOp::TASK, $task->bitrix_task_id, 'update', $this->fingerprints->task($task), $actor);
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function bitrixIds(array $userIds): array
    {
        $users = $this->users->findMany($userIds);

        return $users->map(fn (User $user) => $user->bitrix_user_id
            ?? throw BitrixException::cannotSync("{$user->name} is not a Bitrix24 user, so they cannot be added to this task."))
            ->values()->all();
    }
}
