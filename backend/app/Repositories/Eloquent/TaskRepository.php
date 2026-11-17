<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TaskRepository implements TaskRepositoryInterface
{
    private const SORTS = ['created_at', 'due_date', 'priority', 'title', 'status'];

    public function paginate(?User $visibleTo, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Task::with(['project:id,title', 'assignee:id,name,email']);

        if ($visibleTo) {
            $query->visibleTo($visibleTo);
        }

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort_by'] ?? null, $filters['sort_order'] ?? null);

        return $query->paginate($perPage);
    }

    public function paginateForProject(Project $project, int $perPage): LengthAwarePaginator
    {
        return $project->tasks()->with('assignee:id,name,email')->latest()->paginate($perPage);
    }

    public function loadForDetail(Task $task, User $viewer): Task
    {
        return $task->load($this->detailRelations($viewer));
    }

    public function freshForDetail(Task $task, User $viewer): Task
    {
        return $task->fresh($this->detailRelations($viewer));
    }

    public function save(Task $task): void
    {
        $task->save();
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function syncAccomplices(Task $task, array $userIds): void
    {
        $task->accomplices()->sync($userIds);
    }

    public function syncAuditors(Task $task, array $userIds): void
    {
        $task->auditors()->sync($userIds);
    }

    public function addAuditor(Task $task, int $userId): void
    {
        $task->auditors()->syncWithoutDetaching([$userId]);
    }

    public function removeAuditor(Task $task, int $userId): void
    {
        $task->auditors()->detach($userId);
    }

    public function find(int $id): ?Task
    {
        return Task::find($id);
    }

    public function reload(Task $task): void
    {
        $task->refresh();
    }

    /**
     * What the task page shows. Subtasks are only those the viewer may see.
     *
     * @return array<int|string, mixed>
     */
    private function detailRelations(User $viewer): array
    {
        return [
            'project:id,title', 'assignee:id,name,email', 'accomplices:id,name,email', 'auditors:id,name,email', 'parent:id,title',
            'subtasks' => fn ($query) => $query->with('assignee:id,name,email')
                ->when(! $viewer->hasRole('Administrator'), fn ($q) => $q->visibleTo($viewer))
                ->orderBy('id'),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters['search']);
            $query->where('title', 'like', "%{$search}%");
        }

        foreach (['status', 'priority', 'assigned_to', 'project_id'] as $column) {
            if (isset($filters[$column]) && $filters[$column] !== '') {
                $query->where($column, $filters[$column]);
            }
        }

        // Any closing status (is_final), not only "completed".
        if (isset($filters['finished'])) {
            $filters['finished'] ? $query->finished() : $query->unfinished();
        }

        if (! empty($filters['due_date_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_date_from']);
        }
        if (! empty($filters['due_date_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_date_to']);
        }

        if (! empty($filters['overdue'])) {
            $query->unfinished()->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString());
        }
    }

    private function applySort(Builder $query, ?string $sortBy, ?string $sortOrder): void
    {
        $sortBy = in_array($sortBy, self::SORTS, true) ? $sortBy : 'created_at';
        $sortOrder = in_array(strtolower((string) $sortOrder), ['asc', 'desc'], true) ? strtolower($sortOrder) : 'desc';

        // status/priority are keys: order them by their lookup's `sort`, not alphabetically.
        match ($sortBy) {
            'status' => $query->orderBy(TaskStatus::select('sort')->whereColumn('task_statuses.key', 'tasks.status'), $sortOrder),
            'priority' => $query->orderBy(TaskPriority::select('sort')->whereColumn('task_priorities.key', 'tasks.priority'), $sortOrder),
            default => $query->orderBy($sortBy, $sortOrder),
        };
    }

    public function findByBitrixId(int $bitrixTaskId, bool $withTrashed = false): ?Task
    {
        return Task::query()->when($withTrashed, fn ($q) => $q->withTrashed())->where('bitrix_task_id', $bitrixTaskId)->first();
    }

    public function idByBitrixId(int $bitrixTaskId): ?int
    {
        return Task::where('bitrix_task_id', $bitrixTaskId)->value('id');
    }

    public function bitrixTaskIdOf(int $taskId): ?int
    {
        $bitrixId = Task::withTrashed()->whereKey($taskId)->value('bitrix_task_id');

        return $bitrixId === null ? null : (int) $bitrixId;
    }

    public function restore(Task $task): void
    {
        $task->restore();
    }

    public function markSynced(Task $task): void
    {
        Task::withoutTimestamps(fn () => $task->forceFill(['bitrix_synced_at' => now()])->saveQuietly());
    }

    public function setBitrixChatId(Task $task, int $chatId): void
    {
        Task::withoutTimestamps(fn () => $task->forceFill(['bitrix_chat_id' => $chatId])->saveQuietly());
    }

    public function memberBitrixIds(Task $task): array
    {
        $members = ['accomplice' => [], 'auditor' => []];
        if (! $task->exists) {
            return $members;
        }

        foreach (['accomplice' => $task->accomplices(), 'auditor' => $task->auditors()] as $role => $relation) {
            $members[$role] = $relation->whereNotNull('bitrix_user_id')->orderBy('bitrix_user_id')->pluck('bitrix_user_id')->map(fn ($id) => (int) $id)->all();
        }

        return $members;
    }

    public function allBitrixIds(): array
    {
        return Task::whereNotNull('bitrix_task_id')->pluck('bitrix_task_id')->map(fn ($id) => (int) $id)->all();
    }

    public function deleteByBitrixIds(array $bitrixTaskIds): int
    {
        $gone = Task::whereIn('bitrix_task_id', $bitrixTaskIds)->get();
        $gone->each->delete();

        return $gone->count();
    }

    public function countInBitrix(): int
    {
        return Task::whereNotNull('bitrix_task_id')->count();
    }

    public function chunkInBitrix(int $size, callable $callback): void
    {
        Task::whereNotNull('bitrix_task_id')->orderBy('id')->chunk($size, $callback);
    }
}
