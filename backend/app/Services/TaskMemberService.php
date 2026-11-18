<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\Bitrix24\BitrixTaskMemberPusher;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\ConnectionInterface;

/**
 * Step 4.1: a task's accomplices and auditors, watching and delegating.
 * Tasks in Bitrix change there first (the pusher saves what Bitrix made of
 * it); tasks of local-only projects change here only.
 */
class TaskMemberService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly UserRepositoryInterface $users,
        private readonly BitrixTaskMemberPusher $bitrix,
        private readonly ConnectionInterface $database,
    ) {}

    /**
     * Replaces both lists.
     *
     * @param  list<int>  $accompliceIds
     * @param  list<int>  $auditorIds
     */
    public function setMembers(User $actor, Task $task, array $accompliceIds, array $auditorIds): Task
    {
        if ($this->bitrix->handles($task)) {
            $this->bitrix->setMembers($actor, $task, $accompliceIds, $auditorIds);
        } else {
            $this->database->transaction(function () use ($task, $accompliceIds, $auditorIds): void {
                $this->tasks->syncAccomplices($task, $accompliceIds);
                $this->tasks->syncAuditors($task, $auditorIds);
            });
        }

        return $this->tasks->freshForDetail($task, $actor);
    }

    /** The actor starts or stops following the task. */
    public function watch(User $actor, Task $task, bool $watch): Task
    {
        if ($this->bitrix->handles($task)) {
            $this->bitrix->watch($actor, $task, $watch);
        } else {
            $watch ? $this->tasks->addAuditor($task, $actor->id) : $this->tasks->removeAuditor($task, $actor->id);
        }

        return $this->tasks->freshForDetail($task, $actor);
    }

    /** The responsible person hands the task over and keeps following it. */
    public function delegate(User $actor, Task $task, int $toUserId): Task
    {
        $to = $this->users->findOrFail($toUserId);

        if ($this->bitrix->handles($task)) {
            $this->bitrix->delegate($actor, $task, $to);
        } else {
            $this->database->transaction(function () use ($actor, $task, $to): void {
                $task->assigned_to = $to->id;
                $this->tasks->save($task);
                $this->tasks->addAuditor($task, $actor->id);
            });
        }

        return $this->tasks->freshForDetail($task, $actor);
    }
}
