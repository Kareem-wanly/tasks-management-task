<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Step 4.5: a file attached to a task (a Bitrix attachment, or a local file
 * for tasks outside Bitrix).
 */
class TaskFile extends Model
{
    protected $fillable = ['task_id', 'bitrix_disk_file_id', 'attached_object_id', 'uploaded_by', 'name', 'size', 'mime', 'path', 'created_at'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isInBitrix(): bool
    {
        return $this->attached_object_id !== null;
    }

    protected function casts(): array
    {
        return [
            'bitrix_disk_file_id' => 'integer',
            'attached_object_id' => 'integer',
            'size' => 'integer',
        ];
    }
}
