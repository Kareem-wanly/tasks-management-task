<?php

namespace App\Http\Controllers;

use App\Http\Requests\DelegateTaskRequest;
use App\Http\Requests\UpdateTaskMembersRequest;
use App\Http\Requests\WatchTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskMemberService;

/**
 * Step 4.1: accomplices and auditors.
 */
class TaskMemberController extends Controller
{
    public function __construct(private readonly TaskMemberService $members) {}

    public function update(UpdateTaskMembersRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->members->setMembers(
            $request->user(),
            $task,
            array_map('intval', $request->validated('accomplice_ids')),
            array_map('intval', $request->validated('auditor_ids')),
        ));
    }

    public function watch(WatchTaskRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->members->watch($request->user(), $task, true));
    }

    public function unwatch(WatchTaskRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->members->watch($request->user(), $task, false));
    }

    public function delegate(DelegateTaskRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->members->delegate($request->user(), $task, (int) $request->validated('user_id')));
    }
}
