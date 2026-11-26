<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\SystemRole;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int|null $bitrix_group_id
 */
class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'status',
        'owner_id',
        'start_date',
        'due_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'bitrix_synced_at' => 'datetime',
        'status' => ProjectStatus::class,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function hasMember(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return (int) $this->owner_id === $userId
            || $this->members()->where('users.id', $userId)->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (Project $project): void {
            // Delete one by one so TaskObserver logs each deletion.
            $method = $project->isForceDeleting() ? 'forceDelete' : 'delete';
            $project->tasks()->get()->each(fn ($task) => $task->{$method}());
        });

        static::restoring(function (Project $project): void {
            // Only bring back tasks that were deleted together with the project.
            // deleted_at is still set while "restoring" runs; the small tolerance
            // covers tasks being deleted a moment before the project row is stamped.
            if (! $project->deleted_at instanceof CarbonInterface) {
                return;
            }

            $project->tasks()->onlyTrashed()
                ->where('deleted_at', '>=', $project->deleted_at->copy()->subSeconds(5))
                ->restore();
        });
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    protected function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->hasRole(SystemRole::Administrator)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $user->id));
        });
    }
}
