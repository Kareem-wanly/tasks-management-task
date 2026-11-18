<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Task\TaskResultDTO;
use App\DTOs\Task\TaskResultsDTO;
use App\Models\Task;
use App\Models\TaskResult;
use App\Models\User;

interface TaskResultServiceInterface
{
    /** The results, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskResultsDTO;

    public function add(User $actor, Task $task, TaskResultDTO $result): TaskResultsDTO;

    public function update(User $actor, TaskResult $result, TaskResultDTO $change): TaskResultsDTO;

    public function delete(User $actor, TaskResult $result): TaskResultsDTO;
}
