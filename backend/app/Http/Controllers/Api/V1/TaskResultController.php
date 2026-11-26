<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteTaskResultRequest;
use App\Http\Requests\ShowTaskRequest;
use App\Http\Requests\StoreTaskResultRequest;
use App\Http\Requests\UpdateTaskResultRequest;
use App\Http\Resources\TaskResultsResource;
use App\Models\Task;
use App\Models\TaskResult;
use App\Services\Contracts\TaskResultServiceInterface;

/**
 * Step 4.6: a task's official results. Every answer is the whole list.
 */
final class TaskResultController extends Controller
{
    public function __construct(private readonly TaskResultServiceInterface $results) {}

    public function index(ShowTaskRequest $request, Task $task): TaskResultsResource
    {
        return new TaskResultsResource($this->results->open($task));
    }

    public function store(StoreTaskResultRequest $request, Task $task): TaskResultsResource
    {
        return new TaskResultsResource($this->results->add($request->actor(), $task, $request->toDto()));
    }

    public function update(UpdateTaskResultRequest $request, TaskResult $result): TaskResultsResource
    {
        return new TaskResultsResource($this->results->update($request->actor(), $result, $request->toDto()));
    }

    public function destroy(DeleteTaskResultRequest $request, TaskResult $result): TaskResultsResource
    {
        return new TaskResultsResource($this->results->delete($request->actor(), $result));
    }
}
