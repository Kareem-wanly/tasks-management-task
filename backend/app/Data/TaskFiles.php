<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Task;
use App\Models\TaskFile;
use Illuminate\Support\Collection;

/**
 * A task's files as the service hands them to the controller.
 */
final readonly class TaskFiles
{
    /**
     * @param  Collection<int, TaskFile>  $files  Newest first.
     * @param  bool  $stale  Bitrix could not be reached: this is our last copy.
     */
    public function __construct(
        public Task $task,
        public Collection $files,
        public bool $stale = false,
    ) {}
}
