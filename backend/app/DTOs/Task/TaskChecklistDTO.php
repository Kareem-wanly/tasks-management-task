<?php

declare(strict_types=1);

namespace App\DTOs\Task;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Support\Collection;

/**
 * A task's checklists as the service hands them to the controller.
 */
final readonly class TaskChecklistDTO
{
    /**
     * @param  Collection<int, TaskChecklistItem>  $items  Lists and items, by sort_index.
     * @param  bool  $stale  Bitrix could not be reached: this is our last copy.
     */
    public function __construct(
        public Task $task,
        public Collection $items,
        public bool $stale = false,
    ) {}
}
