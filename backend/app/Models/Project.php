<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
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
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Project $project) {
            if ($project->isForceDeleting()) {
                $project->tasks()->forceDelete();
            } else {
                $project->tasks()->delete();
            }
        });

        static::restoring(function (Project $project) {
            $project->tasks()->withTrashed()->restore();
        });
    }

    public function hasMember(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : (int) $user;

        return (int) $this->owner_id === $userId
            || $this->members()->where('users.id', $userId)->exists();
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Administrator')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $user->id));
        });
    }
}
