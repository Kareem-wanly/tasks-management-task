<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteTimeEntryRequest;
use App\Http\Requests\LogTimeRequest;
use App\Http\Requests\ShowTaskRequest;
use App\Http\Requests\StartTimerRequest;
use App\Http\Requests\StopTimerRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Http\Resources\TimeLogResource;
use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Services\Contracts\TaskTimeServiceInterface;

/**
 * Step 4.4: a task's time log and the employee's timer. Every answer is
 * the whole log, with the totals and the caller's timer.
 */
class TaskTimeController extends Controller
{
    public function __construct(private readonly TaskTimeServiceInterface $time) {}

    public function index(ShowTaskRequest $request, Task $task): TimeLogResource
    {
        return new TimeLogResource($this->time->open($request->user(), $task));
    }

    public function store(LogTimeRequest $request, Task $task): TimeLogResource
    {
        return new TimeLogResource($this->time->add($request->user(), $task, $request->toDto()));
    }

    public function update(UpdateTimeEntryRequest $request, TaskElapsedItem $entry): TimeLogResource
    {
        return new TimeLogResource($this->time->update($request->user(), $entry, $request->toDto()));
    }

    public function destroy(DeleteTimeEntryRequest $request, TaskElapsedItem $entry): TimeLogResource
    {
        return new TimeLogResource($this->time->delete($request->user(), $entry));
    }

    public function start(StartTimerRequest $request, Task $task): TimeLogResource
    {
        return new TimeLogResource($this->time->startTimer($request->user(), $task));
    }

    public function stop(StopTimerRequest $request, Task $task): TimeLogResource
    {
        return new TimeLogResource($this->time->stopTimer($request->user(), $task, $request->toDto()));
    }
}
