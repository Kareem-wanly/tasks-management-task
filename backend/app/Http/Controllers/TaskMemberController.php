<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DelegateTaskRequest;
use App\Http\Requests\UpdateTaskMembersRequest;
use App\Http\Requests\WatchTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\Contracts\TaskMemberServiceInterface;

/**
 * Step 4.1: accomplices and auditors.
 */
final class TaskMemberController extends Controller
{
    public function __construct(private readonly TaskMemberServiceInterface $members) {}

    public function update(UpdateTaskMembersRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->members->setMembers($request->actor(), $task, $request->toDto()));
    }

    public function watch(WatchTaskRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->members->watch($request->actor(), $task, true));
    }

    public function unwatch(WatchTaskRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->members->watch($request->actor(), $task, false));
    }

    public function delegate(DelegateTaskRequest $request, Task $task): TaskResource
    {
        return new TaskResource($this->members->delegate($request->actor(), $task, $request->toDto()));
    }
}
