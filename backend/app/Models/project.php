<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}