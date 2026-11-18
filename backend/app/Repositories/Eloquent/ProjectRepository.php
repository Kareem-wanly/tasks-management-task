<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
    private const SORTS = ['created_at', 'updated_at', 'start_date', 'due_date', 'title'];

    private const WITH_MEMBERS = ['owner:id,name,email', 'members:id,name,email'];

    public function paginateAccessible(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Project::query()->accessibleBy($user);

        if (! empty($filters['search'])) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters['search']);
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['member_id'])) {
            $query->whereHas('members', fn ($q) => $q->where('users.id', $filters['member_id']));
        }

        $sort = $filters['sort'] ?? 'created_at';
        if (in_array($sort, self::SORTS, true)) {
            $query->orderBy($sort, strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc');
        }

        // The same counts as the project page, so list rows draw the progress bar.
        return $query->with('owner:id,name,email')
            ->withCount(['members', ...$this->taskCounts()])
            ->paginate($perPage);
    }

    public function loadForDetail(Project $project): Project
    {
        return $project->load(self::WITH_MEMBERS)->loadCount($this->taskCounts());
    }

    public function freshWithMembers(Project $project): Project
    {
        return $project->fresh(self::WITH_MEMBERS);
    }

    public function optionsFor(User $user, bool $includeArchived, int $limit): Collection
    {
        return Project::query()
            ->accessibleBy($user)
            ->when(! $includeArchived, fn ($q) => $q->where('status', '!=', 'archived'))
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'status']);
    }

    public function save(Project $project): void
    {
        $project->save();
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function hasUnfinishedTasks(Project $project): bool
    {
        return $project->tasks()->unfinished()->exists();
    }

    public function members(Project $project): Collection
    {
        return $project->members()->get(['users.id', 'name', 'email']);
    }

    public function hasMember(Project $project, int $userId): bool
    {
        return $project->members()->where('users.id', $userId)->exists();
    }

    public function attachMember(Project $project, int $userId, string $role): void
    {
        $project->members()->attach($userId, ['role' => $role]);
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
        return Project::where('bitrix_group_id', $bitrixGroupId)->value('id');
    }

    public function bitrixGroupIdOf(int $projectId): ?int
    {
        $groupId = Project::withTrashed()->whereKey($projectId)->value('bitrix_group_id');

        return $groupId === null ? null : (int) $groupId;
    }

    public function restore(Project $project): void
    {
        $project->restore();
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
        return Project::whereNotNull('bitrix_group_id')->pluck('bitrix_group_id')->map(fn ($id): int => (int) $id)->all();
    }

    public function deleteByBitrixIds(array $bitrixGroupIds): int
    {
        $gone = Project::whereIn('bitrix_group_id', $bitrixGroupIds)->get();
        $gone->each->delete();

        return $gone->count();
    }

    /** @return array<string|int, mixed> */
    private function taskCounts(): array
    {
        return [
            'tasks',
            'tasks as completed_tasks_count' => fn ($q) => $q->finished(),
            'tasks as in_progress_tasks_count' => fn ($q) => $q->where('status', 'in_progress'),
        ];
    }
}
