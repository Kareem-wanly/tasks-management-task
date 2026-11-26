<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Repositories\Concerns\ReadsColumns;
use App\Repositories\Contracts\TaskChecklistRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentTaskChecklistRepository implements TaskChecklistRepositoryInterface
{
    use ReadsColumns;

    /** @return Collection<int, TaskChecklistItem> */
    public function forTask(Task $task): Collection
    {
        return $task->checklistItems()->orderBy('sort_index')->orderBy('id')->get();
    }

    public function find(int $id): ?TaskChecklistItem
    {
        return TaskChecklistItem::find($id);
    }

    public function firstList(Task $task): ?TaskChecklistItem
    {
        return $task->checklistItems()->whereNull('parent_id')->orderBy('sort_index')->orderBy('id')->first();
    }

    public function firstItemOf(TaskChecklistItem $list): ?TaskChecklistItem
    {
        return $list->items()->orderBy('sort_index')->orderBy('id')->first();
    }

    public function createList(Task $task, string $title): TaskChecklistItem
    {
        return $task->checklistItems()->create(['title' => $title, 'sort_index' => 0]);
    }

    public function addItem(TaskChecklistItem $list, string $title): TaskChecklistItem
    {
        return $list->task->checklistItems()->create([
            'parent_id' => $list->id,
            'title' => $title,
            'sort_index' => (self::toIntOrNull($list->items()->max('sort_index')) ?? 0) + 1,
        ]);
    }

    public function update(TaskChecklistItem $item, array $attributes): void
    {
        $item->update($attributes);
    }

    public function delete(TaskChecklistItem $item): void
    {
        $item->delete();
    }

    public function reorder(array $items): void
    {
        foreach ($items as $index => $item) {
            $item->update(['sort_index' => $index + 1]);
        }
    }

    /** @return Collection<int, TaskChecklistItem> */
    public function siblingsOf(TaskChecklistItem $item): Collection
    {
        return TaskChecklistItem::where('parent_id', $item->parent_id)
            ->where('id', '!=', $item->id)
            ->orderBy('sort_index')
            ->orderBy('id')
            ->get();
    }

    public function bitrixItems(Task $task): Collection
    {
        return $task->checklistItems()->whereNotNull('bitrix_item_id')->get()->keyBy('bitrix_item_id');
    }

    public function save(TaskChecklistItem $item): void
    {
        $item->save();
    }

    public function deleteBitrixItemsExcept(Task $task, array $bitrixItemIds): void
    {
        $task->checklistItems()->whereNotNull('bitrix_item_id')->whereNotIn('bitrix_item_id', $bitrixItemIds ?: [0])->delete();
    }
}
