<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskMemberRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property int|null $bitrix_task_id
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $bitrix_synced_at
 */
class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'allow_time_tracking',
        'time_estimate',
        'completed_at',
        'project_id',
        'parent_id',
        'assigned_to',
        'created_by',
    ];

    protected $appends = ['is_overdue'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this, \Illuminate\Database\Eloquent\Relations\Pivot> */
    public function accomplices(): BelongsToMany
    {
        return $this->members(TaskMemberRole::Accomplice);
    }

    /** @return BelongsToMany<User, $this, \Illuminate\Database\Eloquent\Relations\Pivot> */
    public function auditors(): BelongsToMany
    {
        return $this->members(TaskMemberRole::Auditor);
    }

    /**
     * Everyone on the task_members table (accomplices and auditors).
     *
     * @return BelongsToMany<User, $this, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function allMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_members');
    }

    /** @return HasMany<TaskChecklistItem, $this> */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class);
    }

    /**
     * @return HasMany<TaskResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(TaskResult::class);
    }

    /**
     * @return HasMany<TaskFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(TaskFile::class);
    }

    /**
     * @return HasMany<TaskElapsedItem, $this>
     */
    public function elapsedItems(): HasMany
    {
        return $this->hasMany(TaskElapsedItem::class);
    }

    /**
     * Step 4.7: the task chat (replaced comments).
     *
     * @return HasMany<TaskChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(TaskChatMessage::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Tasks of a project linked to a Bitrix group, and tasks without a
     * project, live in Bitrix24. Local-only projects (old demo data) are not in Bitrix.
     */
    public function syncsWithBitrix(): bool
    {
        return $this->project_id === null || $this->project?->bitrix_group_id !== null;
    }

    /**
     * Already created in Bitrix24, and still synced with it.
     *
     * @phpstan-assert-if-true !null $this->bitrix_task_id
     */
    public function isInBitrix(): bool
    {
        return $this->bitrix_task_id !== null && $this->syncsWithBitrix();
    }

    /** The Bitrix24 task ID; only for a task already in Bitrix24 (see isInBitrix()). */
    public function bitrixId(): int
    {
        return $this->bitrix_task_id ?? throw new LogicException("Task {$this->id} is not in Bitrix24.");
    }

    /** Whether the task is in a closing status (is_final), not just "completed". */
    public function isFinished(): bool
    {
        return in_array($this->status, TaskStatus::finalKeys(), true);
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
            'bitrix_synced_at' => 'datetime',
            'bitrix_chat_id' => 'integer',
            'allow_time_tracking' => 'boolean',
            'time_estimate' => 'integer',
            'time_spent' => 'integer',
        ];
    }

    /** @return Attribute<bool, never> */
    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->due_date
                && ! $this->isFinished()
                && $this->due_date < now()
        );
    }

    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            if ($task->isDirty('status') || ! $task->exists) {
                $task->completed_at = $task->isFinished()
                    ? ($task->completed_at ?? now())
                    : null;
            }
        });
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    protected function scopeFinished(Builder $query): Builder
    {
        return $query->whereIn('status', TaskStatus::finalKeys());
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    protected function scopeUnfinished(Builder $query): Builder
    {
        return $query->whereNotIn('status', TaskStatus::finalKeys());
    }

    /**
     * Query form of TaskPolicy::canSee(), for non-admin task lists.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    protected function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->whereHas('project', fn (Builder $p) => $p->accessibleBy($user))
                ->orWhere('created_by', $user->id)
                ->orWhere('assigned_to', $user->id)
                ->orWhereHas('allMembers', fn (Builder $member) => $member->whereKey($user->id));
        });
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    protected function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now())->unfinished();
    }

    /**
     * People in one task_members role; sync() on it leaves the other role alone.
     *
     * @return BelongsToMany<User, $this, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    private function members(TaskMemberRole $role): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_members')
            ->withPivotValue('role', $role->value)
            ->withTimestamps();
    }
}
