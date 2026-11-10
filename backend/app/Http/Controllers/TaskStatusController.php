<?php

namespace App\Http\Controllers;

use App\Models\TaskLookup;
use App\Models\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskStatusController extends TaskLookupController
{
    protected function model(): string
    {
        return TaskStatus::class;
    }

    protected function extraRules(?TaskLookup $lookup): array
    {
        // Bitrix decides which of its statuses close a task
        return $lookup?->is_system ? [] : ['is_final' => ['boolean']];
    }

    public function store(Request $request): JsonResponse
    {
        return $this->storeLookup($request);
    }

    public function update(Request $request, TaskStatus $taskStatus): JsonResponse
    {
        return $this->updateLookup($request, $taskStatus);
    }

    public function destroy(TaskStatus $taskStatus): JsonResponse
    {
        return $this->destroyLookup($taskStatus);
    }
}
