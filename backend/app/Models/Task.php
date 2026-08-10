<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected function casts(): array
    {
        return [
            'due_date'     => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    
    protected static function booted(): void
    {
        static::updating(function (Task $task) {
            if ($task->isDirty('status')) {
                if ($task->status === 'completed') {
                    $task->completed_at = now();
                } else {
                    $task->completed_at = null;
                }
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

    
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now())
                     ->where('status', '!=', 'completed');
    }
}