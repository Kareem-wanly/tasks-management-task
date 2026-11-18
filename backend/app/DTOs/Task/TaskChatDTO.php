<?php

declare(strict_types=1);

namespace App\DTOs\Task;

use App\Models\Task;
use App\Models\TaskChatMessage;
use Illuminate\Support\Collection;

/**
 * A task's chat as the service hands it to the controller.
 */
final readonly class TaskChatDTO
{
    /**
     * @param  Collection<int, TaskChatMessage>  $messages  The newest ones, oldest first.
     * @param  bool  $stale  Bitrix could not be reached: this is our last copy.
     */
    public function __construct(
        public Task $task,
        public Collection $messages,
        public bool $stale = false,
    ) {}
}
