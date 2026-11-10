<?php

namespace App\Http\Controllers;

use App\Models\TaskPriority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskPriorityController extends TaskLookupController
{
    protected function model(): string
    {
        return TaskPriority::class;
    }

    public function store(Request $request): JsonResponse
    {
        return $this->storeLookup($request);
    }

    public function update(Request $request, TaskPriority $taskPriority): JsonResponse
    {
        return $this->updateLookup($request, $taskPriority);
    }

    public function destroy(TaskPriority $taskPriority): JsonResponse
    {
        return $this->destroyLookup($taskPriority);
    }
}
