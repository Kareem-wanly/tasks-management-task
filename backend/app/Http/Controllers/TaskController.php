<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteTaskRequest;
use App\Http\Requests\ListProjectTasksRequest;
use App\Http\Requests\ListTasksRequest;
use App\Http\Requests\ShowTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(ListTasksRequest $request): AnonymousResourceCollection
    {
        return TaskResource::collection($this->tasks->list($request->user(), $request->filters(), $request->perPage()));
    }

    public function indexByProject(ListProjectTasksRequest $request, Project $project): JsonResponse
    {
        return response()->json($this->tasks->listForProject($project, $request->perPage()));
    }

    public function show(ShowTaskRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->tasks->show($request->user(), $task));
    }

    public function storeByProject(StoreTaskRequest $request, Project $project): TaskResource
    {
        return new TaskResource($this->tasks->create($request->user(), $project, $request->validated()));
    }

    /** A task without a project: a Bitrix personal task. */
    public function store(StoreTaskRequest $request): TaskResource
    {
        return new TaskResource($this->tasks->create($request->user(), null, $request->validated()));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->tasks->update($request->user(), $task, $request->validated()));
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->tasks->changeStatus($request->user(), $task, $request->validated('status')));
    }

    public function destroy(DeleteTaskRequest $request, Task $task): JsonResponse
    {
        $this->tasks->delete($request->user(), $task);

        return response()->json(['message' => 'Task deleted successfully']);
    }
}
