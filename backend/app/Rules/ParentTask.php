<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Step 4.2: a parent must be a task the user can see, and never the task
 * itself or one of its own subtasks (that would make a loop).
 */
class ParentTask implements ValidationRule
{
    /** Bitrix nests deeper, but a chain this long is a loop in practice. */
    private const MAX_DEPTH = 50;

    public function __construct(private readonly User $user, private readonly ?Task $task = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parent = is_numeric($value) ? resolve(TaskRepositoryInterface::class)->find((int) $value) : null;

        if (! $parent || ! $this->user->can('view', $parent)) {
            $fail('The selected parent task does not exist.');

            return;
        }

        if ($this->task instanceof Task && $this->isOwnSubtree($parent, $this->task)) {
            $fail('A task cannot be a subtask of itself or of its own subtasks.');
        }
    }

    private function isOwnSubtree(Task $parent, Task $task): bool
    {
        for ($node = $parent, $depth = 0; $node && $depth < self::MAX_DEPTH; $node = $node->parent, $depth++) {
            if ($node->id === $task->id) {
                return true;
            }
        }

        return false;
    }
}
