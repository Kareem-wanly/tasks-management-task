<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowTaskRequest;
use App\Http\Resources\TaskHistoryResource;
use App\Models\Task;
use App\Services\Contracts\TaskHistoryServiceInterface;

/**
 * Step 4.8: a task's change history (read only).
 */
final class TaskHistoryController extends Controller
{
    public function __construct(private readonly TaskHistoryServiceInterface $history) {}

    public function index(ShowTaskRequest $request, Task $task): TaskHistoryResource
    {
        return new TaskHistoryResource($this->history->forTask($task));
    }
}
