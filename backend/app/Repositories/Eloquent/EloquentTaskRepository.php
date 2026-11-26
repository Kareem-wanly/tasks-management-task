<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Task\TaskFiltersDTO;
use App\Enums\SystemRole;
use App\Enums\TaskMemberRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentTaskRepository implements TaskRepositoryInterface
{
    private const SORTS = ['created_at', 'due_date', 'priority', 'title', 'status'];

    /** @return LengthAwarePaginator<int, Task> */
    public function paginate(?User $visibleTo, TaskFiltersDTO $filters, int $perPage): LengthAwarePaginator
    {
        $query = Task::with(['project', 'assignee']);

        if ($visibleTo instanceof User) {
            $query->visibleTo($visibleTo);
        }

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters->sortBy, $filters->sortOrder);

        return $query->paginate($perPage);
    }

    /** @return LengthAwarePaginator<int, Task> */
    public function paginateForProject(Project $project, int $perPage): LengthAwarePaginator
    {
        return $project->tasks()->with('assignee')->latest()->paginate($perPage);
    }

    public function loadForDetail(Task $task, User $viewer): Task
    {
        return $task->load($this->detailRelations($viewer));
    }

    public function freshForDetail(Task $task, User $viewer): Task
    {
        return $task->fresh($this->detailRelations($viewer)) ?? throw (new ModelNotFoundException)->setModel(Task::class, [$task->id]);
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
        $members = [TaskMemberRole::Accomplice->value => [], TaskMemberRole::Auditor->value => []];
        if (! $task->exists) {
            return $members;
        }

        foreach ([TaskMemberRole::Accomplice->value => $task->accomplices(), TaskMemberRole::Auditor->value => $task->auditors()] as $role => $relation) {
            $members[$role] = array_values($relation->whereNotNull('bitrix_user_id')->orderBy('bitrix_user_id')->pluck('bitrix_user_id')->map(fn ($id): int => (int) $id)->all());
        }

        return $members;
    }

    public function allBitrixIds(): array
    {
        return array_values(Task::whereNotNull('bitrix_task_id')->pluck('bitrix_task_id')->map(fn ($id): int => (int) $id)->all());
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

    /**
     * What the task page shows. Subtasks are only those the viewer may see.
     *
     * @return array<int|string, mixed>
     */
    private function detailRelations(User $viewer): array
    {
        return [
            'project', 'assignee', 'accomplices', 'auditors', 'parent',
            'subtasks' => fn ($query) => $query->with('assignee')
                ->when(! $viewer->hasRole(SystemRole::Administrator), fn ($q) => $q->visibleTo($viewer))
                ->orderBy('id'),
        ];
    }

    /** @param  Builder<Task>  $query */
    private function applyFilters(Builder $query, TaskFiltersDTO $filters): void
    {
        if ($filters->search !== null) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters->search);
            $query->where('title', 'like', "%{$search}%");
        }

        foreach (['status' => $filters->status, 'priority' => $filters->priority, 'assigned_to' => $filters->assignedTo, 'project_id' => $filters->projectId] as $column => $value) {
            if ($value !== null) {
                $query->where($column, $value);
            }
        }

        // Any closing status (is_final), not only "completed".
        if ($filters->finished !== null) {
            $filters->finished ? $query->finished() : $query->unfinished();
        }

        if ($filters->dueDateFrom !== null) {
            $query->whereDate('due_date', '>=', $filters->dueDateFrom);
        }
        if ($filters->dueDateTo !== null) {
            $query->whereDate('due_date', '<=', $filters->dueDateTo);
        }

        if ($filters->overdue) {
            $query->unfinished()->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString());
        }
    }

    /** @param  Builder<Task>  $query */
    private function applySort(Builder $query, ?string $sortBy, ?string $sortOrder): void
    {
        $sortBy = in_array($sortBy, self::SORTS, true) ? $sortBy : 'created_at';
        $sortOrder = in_array(strtolower((string) $sortOrder), ['asc', 'desc'], true) ? strtolower((string) $sortOrder) : 'desc';

        // status/priority are keys: order them by their lookup's `sort`, not alphabetically.
        match ($sortBy) {
            'status' => $query->orderBy(TaskStatus::select('sort')->whereColumn('task_statuses.key', 'tasks.status'), $sortOrder),
            'priority' => $query->orderBy(TaskPriority::select('sort')->whereColumn('task_priorities.key', 'tasks.priority'), $sortOrder),
            default => $query->orderBy($sortBy, $sortOrder),
        };
    }
}
