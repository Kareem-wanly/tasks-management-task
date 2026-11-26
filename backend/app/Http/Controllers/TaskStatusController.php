<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteTaskLookupRequest;
use App\Http\Requests\ListTaskLookupsRequest;
use App\Http\Requests\StoreTaskStatusLookupRequest;
use App\Http\Requests\UpdateTaskStatusLookupRequest;
use App\Models\TaskStatus;
use App\Services\TaskLookupService;
use Illuminate\Http\JsonResponse;

class TaskStatusController extends Controller
{
    public function __construct(private readonly TaskLookupService $lookups) {}

    public function index(ListTaskLookupsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->lookups->list(TaskStatus::class)]);
    }

    public function store(StoreTaskStatusLookupRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->lookups->create(TaskStatus::class, $request->validated())], 201);
    }

    public function update(UpdateTaskStatusLookupRequest $request, TaskStatus $taskStatus): JsonResponse
    {
        return response()->json(['data' => $this->lookups->update($taskStatus, $request->validated())]);
    }

    public function destroy(DeleteTaskLookupRequest $request, TaskStatus $taskStatus): JsonResponse
    {
        $this->lookups->delete($taskStatus);

        return response()->json(['message' => 'Deleted successfully']);
    }
}
