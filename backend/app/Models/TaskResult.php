<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Step 4.6: an official result of a task.
 *
 * @property-read Task $task
 */
class TaskResult extends Model
{
    protected $fillable = ['task_id', 'bitrix_result_id', 'author_id', 'text', 'status', 'created_at', 'updated_at'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected function casts(): array
    {
        return ['bitrix_result_id' => 'integer'];
    }
}
