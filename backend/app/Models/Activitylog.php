<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
    'user_id',
    'action',
    'subject_type',
    'subject_id',
    'description',
    'metadata',
    'project_id',
    'task_id',
];

protected function casts(): array
{
    return [
        'metadata' => 'array',
    ];
}

public function subject()
{
    return $this->morphTo();
}
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}