<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Task\StopTimerDTO;
use App\DTOs\Task\TimeEntryDTO;
use App\DTOs\Task\TimeLogDTO;
use App\Models\Task;
use App\Models\TaskElapsedItem;
use App\Models\User;
use Illuminate\Support\Facades\Log;

interface TaskTimeServiceInterface
{
    /** The log, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(User $viewer, Task $task): TimeLogDTO;

    public function add(User $actor, Task $task, TimeEntryDTO $time): TimeLogDTO;

    public function update(User $actor, TaskElapsedItem $entry, TimeEntryDTO $time): TimeLogDTO;

    public function delete(User $actor, TaskElapsedItem $entry): TimeLogDTO;

    /** Starts the actor's timer here; a timer running on another task is stopped and logged. */
    public function startTimer(User $actor, Task $task): TimeLogDTO;

    /** Stops the actor's timer on this task; a second stop (double click, other tab) changes nothing. */
    public function stopTimer(User $actor, Task $task, StopTimerDTO $stop): TimeLogDTO;
}
