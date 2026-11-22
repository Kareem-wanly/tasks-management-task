<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Task\AddChecklistItemDTO;
use App\DTOs\Task\MoveChecklistItemDTO;
use App\DTOs\Task\TaskChecklistDTO;
use App\DTOs\Task\UpdateChecklistItemDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\Task\ChecklistListNotMovableException;
use App\Integrations\Bitrix24\BitrixChecklistSync;
use App\Integrations\Bitrix24\Contracts\BitrixChecklistRepositoryInterface;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use App\Repositories\Contracts\TaskChecklistRepositoryInterface;
use App\Services\Contracts\TaskChecklistServiceInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.3: a task's checklists. Bitrix sends no events for them, so they
 * are fetched from Bitrix each time the task is opened. Tasks in Bitrix
 * change there first; tasks of local-only projects change here only.
 */
final readonly class TaskChecklistService implements TaskChecklistServiceInterface
{
    /** The name Bitrix gives a list made without a title. */
    private const DEFAULT_LIST_TITLE = 'BX_CHECKLIST_1';

    public function __construct(
        private TaskChecklistRepositoryInterface $checklists,
        private BitrixChecklistRepositoryInterface $bitrixChecklists,
        private BitrixChecklistSync $checklistSync,
        private ConnectionInterface $database,
    ) {}

    /** The checklist, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskChecklistDTO
    {
        $stale = false;

        if ($task->isInBitrix()) {
            try {
                $this->refreshFromBitrix($task);
            } catch (BitrixException $e) {
                Log::warning('Bitrix24 checklist not refreshed; showing our copy.', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                $stale = true;
            }
        }

        return $this->current($task, $stale);
    }

    /** Adds an item to the given list, or to the first one. */
    public function add(User $actor, Task $task, AddChecklistItemDTO $item): TaskChecklistDTO
    {
        $title = $item->title;
        $listId = $item->listId;
        $list = $listId ? $this->checklists->find($listId) : $this->checklists->firstList($task);

        if ($task->isInBitrix()) {
            $this->ensureSignedInWithBitrix($actor);
            $this->bitrixChecklists->add($actor, $task->bitrix_task_id, $title, $list?->bitrix_item_id);
            $this->refreshFromBitrix($task);
        } else {
            $this->checklists->addItem($list ?? $this->checklists->createList($task, self::DEFAULT_LIST_TITLE), $title);
        }

        return $this->current($task);
    }

    public function update(User $actor, TaskChecklistItem $item, UpdateChecklistItemDTO $changes): TaskChecklistDTO
    {
        $task = $item->task;

        if ($changes->title !== null) {
            $task->isInBitrix()
                ? $this->changeInBitrix($actor, $task, fn () => $this->bitrixChecklists->rename($actor, $task->bitrix_task_id, (int) $item->bitrix_item_id, $changes->title))
                : $this->checklists->update($item, ['title' => $changes->title]);
        }

        if ($changes->isComplete !== null) {
            $task->isInBitrix()
                // The item as the rename above left it.
                ? $this->changeInBitrix($actor, $task, fn () => $this->bitrixChecklists->setComplete($actor, $task->bitrix_task_id, (int) $this->checklists->find($item->id)->bitrix_item_id, $changes->isComplete))
                : $this->checklists->update($item, ['is_complete' => $changes->isComplete]);
        }

        return $this->current($task);
    }

    public function delete(User $actor, TaskChecklistItem $item): TaskChecklistDTO
    {
        $task = $item->task;

        $task->isInBitrix()
            ? $this->changeInBitrix($actor, $task, fn () => $this->bitrixChecklists->delete($actor, $task->bitrix_task_id, (int) $item->bitrix_item_id))
            : $this->checklists->delete($item);

        return $this->current($task);
    }

    /** Puts an item right after another item of its list, or first ($afterId null). */
    public function move(User $actor, TaskChecklistItem $item, MoveChecklistItemDTO $position): TaskChecklistDTO
    {
        $afterId = $position->afterId;

        if ($item->isList()) {
            throw new ChecklistListNotMovableException;
        }

        $task = $item->task;
        $after = $afterId ? $this->checklists->find($afterId) : null;

        if ($task->isInBitrix()) {
            $this->moveInBitrix($actor, $task, $item, $after);
        } else {
            $siblings = $this->checklists->siblingsOf($item)->all();
            $position = $after instanceof TaskChecklistItem ? array_search($after->id, array_map(fn (TaskChecklistItem $s) => $s->id, $siblings), true) + 1 : 0;
            array_splice($siblings, $position, 0, [$item]);
            $this->database->transaction(fn () => $this->checklists->reorder($siblings));
        }

        return $this->current($task);
    }

    private function current(Task $task, bool $stale = false): TaskChecklistDTO
    {
        return new TaskChecklistDTO($task, $this->checklists->forTask($task), $stale);
    }

    /** Brings our copy of the task's checklist up to date. */
    private function refreshFromBitrix(Task $task): void
    {
        $this->checklistSync->sync($task, $this->bitrixChecklists->list($task->bitrix_task_id));
    }

    /** One change in Bitrix, then our copy as Bitrix has it. */
    private function changeInBitrix(User $actor, Task $task, callable $change): void
    {
        $this->ensureSignedInWithBitrix($actor);
        $change();
        $this->refreshFromBitrix($task);
    }

    /** After $after, or first (when it isn't already). Nothing to send leaves Bitrix alone. */
    private function moveInBitrix(User $actor, Task $task, TaskChecklistItem $item, ?TaskChecklistItem $after): void
    {
        $first = $this->checklists->firstItemOf($item->parent);
        $firstId = $first instanceof TaskChecklistItem && $first->id !== $item->id ? $first->bitrix_item_id : null;

        if ($after instanceof TaskChecklistItem || $firstId !== null) {
            $this->ensureSignedInWithBitrix($actor);
            $this->bitrixChecklists->move($actor, $task->bitrix_task_id, (int) $item->bitrix_item_id, $after?->bitrix_item_id, $firstId);
        }

        $this->refreshFromBitrix($task);
    }

    /** Changes go to Bitrix with the employee's own token. */
    private function ensureSignedInWithBitrix(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }
}
