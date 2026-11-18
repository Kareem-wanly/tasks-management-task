<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Task\TaskChecklistDTO;
use App\Exceptions\BusinessRuleException;
use App\Integrations\Bitrix24\BitrixChecklistPusher;
use App\Integrations\Bitrix24\BitrixException;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use App\Repositories\Contracts\TaskChecklistRepositoryInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.3: a task's checklists. Bitrix sends no events for them, so they
 * are fetched from Bitrix each time the task is opened. Tasks in Bitrix
 * change there first; tasks of local-only projects change here only.
 */
class TaskChecklistService
{
    /** The name Bitrix gives a list made without a title. */
    private const DEFAULT_LIST_TITLE = 'BX_CHECKLIST_1';

    public function __construct(
        private readonly TaskChecklistRepositoryInterface $checklists,
        private readonly BitrixChecklistPusher $bitrix,
        private readonly ConnectionInterface $database,
    ) {}

    /** The checklist, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskChecklistDTO
    {
        $stale = false;

        if ($this->bitrix->handles($task)) {
            try {
                $this->bitrix->refresh($task);
            } catch (BitrixException $e) {
                Log::warning('Bitrix24 checklist not refreshed; showing our copy.', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                $stale = true;
            }
        }

        return $this->current($task, $stale);
    }

    /** Adds an item to the given list, or to the first one. */
    public function add(User $actor, Task $task, string $title, ?int $listId): TaskChecklistDTO
    {
        $list = $listId ? $this->checklists->find($listId) : $this->checklists->firstList($task);

        if ($this->bitrix->handles($task)) {
            $this->bitrix->add($actor, $task, $title, $list);
        } else {
            $this->checklists->addItem($list ?? $this->checklists->createList($task, self::DEFAULT_LIST_TITLE), $title);
        }

        return $this->current($task);
    }

    /** @param  array{title?: string, is_complete?: bool}  $changes */
    public function update(User $actor, TaskChecklistItem $item, array $changes): TaskChecklistDTO
    {
        $task = $item->task;

        if (isset($changes['title'])) {
            $this->bitrix->handles($task)
                ? $this->bitrix->rename($actor, $item, $changes['title'])
                : $this->checklists->update($item, ['title' => $changes['title']]);
        }

        if (isset($changes['is_complete'])) {
            $this->bitrix->handles($task)
                ? $this->bitrix->toggle($actor, $this->checklists->find($item->id), $changes['is_complete'])
                : $this->checklists->update($item, ['is_complete' => $changes['is_complete']]);
        }

        return $this->current($task);
    }

    public function delete(User $actor, TaskChecklistItem $item): TaskChecklistDTO
    {
        $task = $item->task;

        $this->bitrix->handles($task) ? $this->bitrix->delete($actor, $item) : $this->checklists->delete($item);

        return $this->current($task);
    }

    /** Puts an item right after another item of its list, or first ($afterId null). */
    public function move(User $actor, TaskChecklistItem $item, ?int $afterId): TaskChecklistDTO
    {
        if ($item->isList()) {
            throw new BusinessRuleException('Lists cannot be moved.');
        }

        $task = $item->task;
        $after = $afterId ? $this->checklists->find($afterId) : null;

        if ($this->bitrix->handles($task)) {
            $this->bitrix->move($actor, $item, $after, $this->checklists->firstItemOf($item->parent));
        } else {
            $siblings = $this->checklists->siblingsOf($item)->all();
            $position = $after instanceof TaskChecklistItem ? array_search($after->id, array_map(fn ($s) => $s->id, $siblings), true) + 1 : 0;
            array_splice($siblings, $position, 0, [$item]);
            $this->database->transaction(fn () => $this->checklists->reorder($siblings));
        }

        return $this->current($task);
    }

    private function current(Task $task, bool $stale = false): TaskChecklistDTO
    {
        return new TaskChecklistDTO($task, $this->checklists->forTask($task), $stale);
    }
}
