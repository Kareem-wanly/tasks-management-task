<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Step 4.3: one checklist (no parent) or one item of it.
 *
 * @property-read Task $task
 */
class TaskChecklistItem extends Model
{
    protected $fillable = ['task_id', 'bitrix_item_id', 'parent_id', 'title', 'is_complete', 'sort_index'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<TaskChecklistItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<TaskChecklistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** A checklist rather than an item of one. */
    public function isList(): bool
    {
        return $this->parent_id === null;
    }

    protected function casts(): array
    {
        return [
            'bitrix_item_id' => 'integer',
            'is_complete' => 'boolean',
            'sort_index' => 'integer',
        ];
    }
}
