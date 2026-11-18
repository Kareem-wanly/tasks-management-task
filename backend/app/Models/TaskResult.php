<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Step 4.6: an official result of a task.
 */
class TaskResult extends Model
{
    protected $fillable = ['task_id', 'bitrix_result_id', 'author_id', 'text', 'status', 'created_at', 'updated_at'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected function casts(): array
    {
        return ['bitrix_result_id' => 'integer'];
    }
}
