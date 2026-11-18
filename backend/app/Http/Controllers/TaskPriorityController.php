<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteTaskLookupRequest;
use App\Http\Requests\ListTaskLookupsRequest;
use App\Http\Requests\StoreTaskPriorityLookupRequest;
use App\Http\Requests\UpdateTaskPriorityLookupRequest;
use App\Models\TaskPriority;
use App\Services\TaskLookupService;
use Illuminate\Http\JsonResponse;

class TaskPriorityController extends Controller
{
    public function __construct(private readonly TaskLookupService $lookups) {}

    public function index(ListTaskLookupsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->lookups->list(TaskPriority::class)]);
    }

    public function store(StoreTaskPriorityLookupRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->lookups->create(TaskPriority::class, $request->validated())], 201);
    }

    public function update(UpdateTaskPriorityLookupRequest $request, TaskPriority $taskPriority): JsonResponse
    {
        return response()->json(['data' => $this->lookups->update($taskPriority, $request->validated())]);
    }

    public function destroy(DeleteTaskLookupRequest $request, TaskPriority $taskPriority): JsonResponse
    {
        $this->lookups->delete($taskPriority);

        return response()->json(['message' => 'Deleted successfully']);
    }
}
