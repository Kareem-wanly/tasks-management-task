<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteTaskLookupRequest;
use App\Http\Requests\ListTaskLookupsRequest;
use App\Http\Requests\StoreTaskStatusLookupRequest;
use App\Http\Requests\UpdateTaskStatusLookupRequest;
use App\Http\Resources\TaskStatusResource;
use App\Models\TaskStatus;
use App\Services\Contracts\TaskLookupServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TaskStatusController extends Controller
{
    public function __construct(private readonly TaskLookupServiceInterface $lookups) {}

    public function index(ListTaskLookupsRequest $request): AnonymousResourceCollection
    {
        return TaskStatusResource::collection($this->lookups->list(TaskStatus::class));
    }

    public function store(StoreTaskStatusLookupRequest $request): JsonResponse
    {
        return (new TaskStatusResource($this->lookups->create(TaskStatus::class, $request->toDto())))->response()->setStatusCode(201);
    }

    public function update(UpdateTaskStatusLookupRequest $request, TaskStatus $taskStatus): TaskStatusResource
    {
        return new TaskStatusResource($this->lookups->update($taskStatus, $request->toDto()));
    }

    public function destroy(DeleteTaskLookupRequest $request, TaskStatus $taskStatus): JsonResponse
    {
        $this->lookups->delete($taskStatus);

        return response()->json(['message' => 'Deleted successfully']);
    }
}
