<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Step 4.4: one entry of a task's time log (Bitrix elapsed item).
 */
class TaskElapsedItem extends Model
{
    protected $fillable = ['task_id', 'bitrix_item_id', 'user_id', 'seconds', 'comment', 'created_date'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'bitrix_item_id' => 'integer',
            'seconds' => 'integer',
            'created_date' => 'datetime',
        ];
    }
}
