<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property Carbon|null $bitrix_synced_at
 */
class Task extends Model
{
    use HasFactory, SoftDeletes;

    /** task_members.role: works on the task besides the responsible person. */
    public const ACCOMPLICE = 'accomplice';

    /** task_members.role: follows the task. */
    public const AUDITOR = 'auditor';

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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accomplices(): BelongsToMany
    {
        return $this->members(self::ACCOMPLICE);
    }

    public function auditors(): BelongsToMany
    {
        return $this->members(self::AUDITOR);
    }

    public function hasMember(User $user): bool
    {
        return $this->exists && DB::table('task_members')->where('task_id', $this->id)->where('user_id', $user->id)->exists();
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(TaskResult::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(TaskFile::class);
    }

    public function elapsedItems(): HasMany
    {
        return $this->hasMany(TaskElapsedItem::class);
    }

    /** Step 4.7: the task chat (replaced comments). */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(TaskChatMessage::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /** Whether the task is in a closing status (is_final), not just "completed". */
    public function isFinished(): bool
    {
        return in_array($this->status, TaskStatus::finalKeys(), true);
    }

    /**
     * Project members see a project's tasks. As in Bitrix, the people on a
     * task always see it, in a project or not: its creator, responsible
     * person, accomplices and auditors. Administrators pass every policy
     * before this is asked.
     */
    public function isVisibleTo(User $user): bool
    {
        return (int) $this->created_by === $user->id
            || (int) $this->assigned_to === $user->id
            || ($this->project_id !== null && $this->project && $this->project->hasMember($user))
            || $this->hasMember($user);
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

    protected function scopeFinished(Builder $query): Builder
    {
        return $query->whereIn('status', TaskStatus::finalKeys());
    }

    protected function scopeUnfinished(Builder $query): Builder
    {
        return $query->whereNotIn('status', TaskStatus::finalKeys());
    }

    /**
     * Query form of isVisibleTo(), for non-admin task lists.
     */
    protected function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->whereHas('project', fn (Builder $p) => $p->accessibleBy($user))
                ->orWhere('created_by', $user->id)
                ->orWhere('assigned_to', $user->id)
                ->orWhereIn('id', DB::table('task_members')->select('task_id')->where('user_id', $user->id));
        });
    }

    protected function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now())->unfinished();
    }

    /** People in one task_members role; sync() on it leaves the other role alone. */
    private function members(string $role): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_members')
            ->withPivotValue('role', $role)
            ->withTimestamps();
    }
}
