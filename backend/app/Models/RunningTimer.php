<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Step 4.4: an employee's running timer (at most one). Stopping it turns
 * the time since started_at into a time log entry in Bitrix.
 */
class RunningTimer extends Model
{
    protected $fillable = ['user_id', 'task_id', 'started_at'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seconds(): int
    {
        return max(0, (int) $this->started_at->diffInSeconds(now()));
    }

    protected function casts(): array
    {
        return ['started_at' => 'datetime'];
    }
}
