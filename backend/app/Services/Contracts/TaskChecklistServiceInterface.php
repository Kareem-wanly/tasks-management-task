<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Task\AddChecklistItemDTO;
use App\DTOs\Task\MoveChecklistItemDTO;
use App\DTOs\Task\TaskChecklistDTO;
use App\DTOs\Task\UpdateChecklistItemDTO;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;

interface TaskChecklistServiceInterface
{
    /** The checklist, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskChecklistDTO;

    /** Adds an item to the given list, or to the first one. */
    public function add(User $actor, Task $task, AddChecklistItemDTO $item): TaskChecklistDTO;

    public function update(User $actor, TaskChecklistItem $item, UpdateChecklistItemDTO $changes): TaskChecklistDTO;

    public function delete(User $actor, TaskChecklistItem $item): TaskChecklistDTO;

    /** Puts an item right after another item of its list, or first ($afterId null). */
    public function move(User $actor, TaskChecklistItem $item, MoveChecklistItemDTO $position): TaskChecklistDTO;
}
