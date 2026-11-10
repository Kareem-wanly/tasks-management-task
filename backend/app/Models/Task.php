<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'project_id',
        'assigned_to',
        'created_by',
    ];

    protected $appends = ['is_overdue'];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
            'bitrix_synced_at' => 'datetime',
        ];
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->due_date
                && ! $this->isFinished()
                && $this->due_date < now()
        );
    }

    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            if ($task->isDirty('status') || ! $task->exists) {
                $task->completed_at = $task->isFinished()
                    ? ($task->completed_at ?? now())
                    : null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
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

    public function scopeFinished(Builder $query): Builder
    {
        return $query->whereIn('status', TaskStatus::finalKeys());
    }

    public function scopeUnfinished(Builder $query): Builder
    {
        return $query->whereNotIn('status', TaskStatus::finalKeys());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now())->unfinished();
    }
}
