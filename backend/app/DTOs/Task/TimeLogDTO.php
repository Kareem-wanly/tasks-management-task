<?php

declare(strict_types=1);

namespace App\DTOs\Task;

use App\Models\RunningTimer;
use App\Models\Task;
use App\Models\TaskElapsedItem;
use Illuminate\Support\Collection;

/**
 * A task's time log, with the viewer's timer, as the service hands it to the controller.
 */
final readonly class TimeLogDTO
{
    /**
     * @param  Collection<int, TaskElapsedItem>  $entries  Newest first.
     * @param  RunningTimer|null  $timer  The viewer's running timer, on this task or another.
     * @param  bool  $stale  Bitrix could not be reached: this is our last copy.
     * @param  Task|null  $stoppedTask  The task whose timer starting this one stopped (and logged).
     */
    public function __construct(
        public Task $task,
        public Collection $entries,
        public ?RunningTimer $timer,
        public bool $stale = false,
        public ?Task $stoppedTask = null,
    ) {}
}
