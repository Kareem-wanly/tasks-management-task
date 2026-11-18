<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Task\DelegateTaskDTO;
use App\DTOs\Task\TaskMembersDTO;
use App\Models\Task;
use App\Models\User;

interface TaskMemberServiceInterface
{
    /** Replaces both lists. */
    public function setMembers(User $actor, Task $task, TaskMembersDTO $members): Task;

    /** The actor starts or stops following the task. */
    public function watch(User $actor, Task $task, bool $watch): Task;

    /** The responsible person hands the task over and keeps following it. */
    public function delegate(User $actor, Task $task, DelegateTaskDTO $data): Task;
}
