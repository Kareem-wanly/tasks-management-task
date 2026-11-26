<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Task\DelegateTaskDTO;
use App\DTOs\Task\TaskMembersDTO;
use App\Integrations\Bitrix24\BitrixTaskMemberPusher;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\TaskMemberServiceInterface;
use Illuminate\Database\ConnectionInterface;

/**
 * Step 4.1: a task's accomplices and auditors, watching and delegating.
 * Tasks in Bitrix change there first (the pusher saves what Bitrix made of
 * it); tasks of local-only projects change here only.
 */
final readonly class TaskMemberService implements TaskMemberServiceInterface
{
    public function __construct(
        private TaskRepositoryInterface $tasks,
        private UserRepositoryInterface $users,
        private BitrixTaskMemberPusher $bitrix,
        private ConnectionInterface $database,
    ) {}

    /** Replaces both lists. */
    public function setMembers(User $actor, Task $task, TaskMembersDTO $members): Task
    {
        if ($this->bitrix->handles($task)) {
            $this->bitrix->setMembers($actor, $task, $members->accompliceIds, $members->auditorIds);
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
        if ($this->bitrix->handles($task)) {
            $this->bitrix->watch($actor, $task, $watch);
        } else {
            $watch ? $this->tasks->addAuditor($task, $actor->id) : $this->tasks->removeAuditor($task, $actor->id);
        }

        return $this->tasks->freshForDetail($task, $actor);
    }

    /** The responsible person hands the task over and keeps following it. */
    public function delegate(User $actor, Task $task, DelegateTaskDTO $data): Task
    {
        $to = $this->users->findOrFail($data->userId);

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
