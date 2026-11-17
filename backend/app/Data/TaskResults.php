<?php

namespace App\Data;

use App\Models\Task;
use App\Models\TaskResult;
use Illuminate\Support\Collection;

/**
 * A task's results as the service hands them to the controller.
 */
final readonly class TaskResults
{
    /**
     * @param  Collection<int, TaskResult>  $results  Newest first.
     * @param  bool  $stale  Bitrix could not be reached: this is our last copy.
     */
    public function __construct(
        public Task $task,
        public Collection $results,
        public bool $stale = false,
    ) {}
}
