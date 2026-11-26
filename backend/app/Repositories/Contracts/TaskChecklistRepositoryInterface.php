<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Support\Collection;

/**
 * A task's checklists: a list is an item without a parent, as in Bitrix.
 */
interface TaskChecklistRepositoryInterface
{
    /**
     * Lists and items, by sort_index.
     *
     * @return Collection<int, TaskChecklistItem>
     */
    public function forTask(Task $task): Collection;

    public function find(int $id): ?TaskChecklistItem;

    /** The task's first list, if it has one. */
    public function firstList(Task $task): ?TaskChecklistItem;

    /** The first item of a list. */
    public function firstItemOf(TaskChecklistItem $list): ?TaskChecklistItem;

    public function createList(Task $task, string $title): TaskChecklistItem;

    /** Added at the end of the list. */
    public function addItem(TaskChecklistItem $list, string $title): TaskChecklistItem;

    /** @param  array<string, mixed>  $attributes */
    public function update(TaskChecklistItem $item, array $attributes): void;

    public function delete(TaskChecklistItem $item): void;

    /**
     * Renumbers a list's items in the given order.
     *
     * @param  list<TaskChecklistItem>  $items
     */
    public function reorder(array $items): void;

    /**
     * The other items of the item's list, in order.
     *
     * @return Collection<int, TaskChecklistItem>
     */
    public function siblingsOf(TaskChecklistItem $item): Collection;

    /**
     * The task's items that came from Bitrix.
     *
     * @return Collection<int, TaskChecklistItem> By Bitrix item ID.
     */
    public function bitrixItems(Task $task): Collection;

    public function save(TaskChecklistItem $item): void;

    /**
     * Deletes the task's Bitrix items not in the list.
     *
     * @param  list<int>  $bitrixItemIds
     */
    public function deleteBitrixItemsExcept(Task $task, array $bitrixItemIds): void;
}
