<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteChecklistItemRequest;
use App\Http\Requests\MoveChecklistItemRequest;
use App\Http\Requests\ShowTaskRequest;
use App\Http\Requests\StoreChecklistItemRequest;
use App\Http\Requests\UpdateChecklistItemRequest;
use App\Http\Resources\TaskChecklistResource;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Services\TaskChecklistService;

/**
 * Step 4.3: a task's checklists. Every answer is the whole checklist.
 */
class TaskChecklistController extends Controller
{
    public function __construct(private readonly TaskChecklistService $checklists) {}

    public function index(ShowTaskRequest $request, Task $task): TaskChecklistResource
    {
        return new TaskChecklistResource($this->checklists->open($task));
    }

    public function store(StoreChecklistItemRequest $request, Task $task): TaskChecklistResource
    {
        $listId = $request->validated('list_id');

        return new TaskChecklistResource($this->checklists->add($request->user(), $task, trim($request->validated('title')), $listId ? (int) $listId : null));
    }

    public function update(UpdateChecklistItemRequest $request, TaskChecklistItem $item): TaskChecklistResource
    {
        return new TaskChecklistResource($this->checklists->update($request->user(), $item, $request->changes()));
    }

    public function destroy(DeleteChecklistItemRequest $request, TaskChecklistItem $item): TaskChecklistResource
    {
        return new TaskChecklistResource($this->checklists->delete($request->user(), $item));
    }

    public function move(MoveChecklistItemRequest $request, TaskChecklistItem $item): TaskChecklistResource
    {
        $afterId = $request->validated('after_id');

        return new TaskChecklistResource($this->checklists->move($request->user(), $item, $afterId === null ? null : (int) $afterId));
    }
}
