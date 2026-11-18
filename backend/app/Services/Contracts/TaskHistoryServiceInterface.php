<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Task\TaskHistoryDTO;
use App\Models\Task;

interface TaskHistoryServiceInterface
{
    public function forTask(Task $task): TaskHistoryDTO;
}
