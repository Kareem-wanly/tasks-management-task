<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteTaskLookupRequest;
use App\Http\Requests\ListTaskLookupsRequest;
use App\Http\Requests\StoreTaskPriorityLookupRequest;
use App\Http\Requests\UpdateTaskPriorityLookupRequest;
use App\Http\Resources\TaskPriorityResource;
use App\Models\TaskPriority;
use App\Services\Contracts\TaskLookupServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TaskPriorityController extends Controller
{
    public function __construct(private readonly TaskLookupServiceInterface $lookups) {}

    public function index(ListTaskLookupsRequest $request): AnonymousResourceCollection
    {
        return TaskPriorityResource::collection($this->lookups->list(TaskPriority::class));
    }

    public function store(StoreTaskPriorityLookupRequest $request): JsonResponse
    {
        return (new TaskPriorityResource($this->lookups->create(TaskPriority::class, $request->toDto())))->response()->setStatusCode(201);
    }

    public function update(UpdateTaskPriorityLookupRequest $request, TaskPriority $taskPriority): TaskPriorityResource
    {
        return new TaskPriorityResource($this->lookups->update($taskPriority, $request->toDto()));
    }

    public function destroy(DeleteTaskLookupRequest $request, TaskPriority $taskPriority): JsonResponse
    {
        $this->lookups->delete($taskPriority);

        return response()->json(['message' => 'Deleted successfully']);
    }
}
