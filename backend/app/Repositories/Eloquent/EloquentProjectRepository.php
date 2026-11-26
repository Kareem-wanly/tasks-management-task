<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Project\ProjectFiltersDTO;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Repositories\Concerns\ReadsColumns;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

final class EloquentProjectRepository implements ProjectRepositoryInterface
{
    use ReadsColumns;

    private const SORTS = ['created_at', 'updated_at', 'start_date', 'due_date', 'title'];

    private const WITH_MEMBERS = ['owner', 'members'];

    /** @return LengthAwarePaginator<int, Project> */
    public function paginateAccessible(User $user, ProjectFiltersDTO $filters, int $perPage): LengthAwarePaginator
    {
        $query = Project::query()->accessibleBy($user);

        if ($filters->search !== null) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters->search);
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->memberId !== null) {
            $memberId = $filters->memberId;
            $query->whereHas('members', fn ($q) => $q->where('users.id', $memberId));
        }

        $sort = $filters->sort ?? 'created_at';
        if (in_array($sort, self::SORTS, true)) {
            $query->orderBy($sort, strtolower($filters->direction ?? 'desc') === 'asc' ? 'asc' : 'desc');
        }

        // The same counts as the project page, so list rows draw the progress bar.
        return $query->with('owner')
            ->withCount(['members', ...$this->taskCounts()])
            ->paginate($perPage);
    }

    public function loadForDetail(Project $project): Project
    {
        return $project->load(self::WITH_MEMBERS)->loadCount($this->taskCounts());
    }

    public function freshWithMembers(Project $project): Project
    {
        return $project->fresh(self::WITH_MEMBERS) ?? throw (new ModelNotFoundException)->setModel(Project::class, [$project->id]);
    }

    /** @return Collection<int, Project> */
    public function optionsFor(User $user, bool $includeArchived, int $limit): Collection
    {
        return Project::query()
            ->accessibleBy($user)
            ->when(! $includeArchived, fn ($q) => $q->where('status', '!=', ProjectStatus::Archived->value))
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'status']);
    }

    public function save(Project $project): void
    {
        $project->save();
    }

    /** With its tasks, one by one so TaskObserver logs each deletion. */
    public function delete(Project $project): void
    {
        $project->tasks()->get()->each(fn (Task $task): ?bool => $task->delete());
        $project->delete();
    }

    public function hasUnfinishedTasks(Project $project): bool
    {
        return $project->tasks()->unfinished()->exists();
    }

    /** @return Collection<int, User> */
    public function members(Project $project): Collection
    {
        return $project->members()->get(['users.id', 'name', 'email']);
    }

    public function hasMember(Project $project, int $userId): bool
    {
        return $project->members()->where('users.id', $userId)->exists();
    }

    public function attachMember(Project $project, int $userId, ProjectMemberRole $role): void
    {
        $project->members()->attach($userId, ['role' => $role->value]);
    }

    public function detachMember(Project $project, int $userId): void
    {
        $project->members()->detach($userId);
    }

    public function findByBitrixGroupId(int $bitrixGroupId, bool $withTrashed = false): ?Project
    {
        return Project::query()->when($withTrashed, fn ($q) => $q->withTrashed())->where('bitrix_group_id', $bitrixGroupId)->first();
    }

    public function idByBitrixGroupId(int $bitrixGroupId): ?int
    {
        return self::toIntOrNull(Project::where('bitrix_group_id', $bitrixGroupId)->value('id'));
    }

    public function bitrixGroupIdOf(int $projectId): ?int
    {
        $groupId = Project::withTrashed()->whereKey($projectId)->value('bitrix_group_id');

        return self::toIntOrNull($groupId);
    }

    /**
     * Brings back only the tasks deleted together with the project; the small
     * tolerance covers tasks deleted a moment before the project row was stamped.
     */
    public function restore(Project $project): void
    {
        $deletedAt = $project->deleted_at;
        $project->restore();

        if ($deletedAt instanceof CarbonInterface) {
            $project->tasks()->onlyTrashed()->where('deleted_at', '>=', $deletedAt->copy()->subSeconds(5))->restore();
        }
    }

    public function markSynced(Project $project): void
    {
        Project::withoutTimestamps(fn () => $project->forceFill(['bitrix_synced_at' => now()])->saveQuietly());
    }

    public function syncMembersWithRoles(Project $project, array $members): void
    {
        $project->members()->sync($members);
    }

    public function allBitrixIds(): array
    {
        return self::toIntList(Project::whereNotNull('bitrix_group_id')->pluck('bitrix_group_id'));
    }

    public function deleteByBitrixIds(array $bitrixGroupIds): int
    {
        $gone = Project::whereIn('bitrix_group_id', $bitrixGroupIds)->get();
        $gone->each(fn (Project $project) => $this->delete($project));

        return $gone->count();
    }

    /** @return array<string|int, mixed> */
    private function taskCounts(): array
    {
        return [
            'tasks',
            'tasks as completed_tasks_count' => fn ($q) => $q->finished(),
            'tasks as in_progress_tasks_count' => fn ($q) => $q->where('status', TaskStatus::IN_PROGRESS_KEY),
        ];
    }
}
