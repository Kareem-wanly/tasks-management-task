<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowTaskRequest;
use App\Http\Resources\TaskHistoryResource;
use App\Models\Task;
use App\Services\TaskHistoryService;

/**
 * Step 4.8: a task's change history (read only).
 */
class TaskHistoryController extends Controller
{
    public function __construct(private readonly TaskHistoryService $history) {}

    public function index(ShowTaskRequest $request, Task $task): TaskHistoryResource
    {
        return new TaskHistoryResource($this->history->forTask($task));
    }
}
