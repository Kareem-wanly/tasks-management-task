<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Step 4.7: a message in a task's chat (replaces comments).
 */
class TaskChatMessage extends Model
{
    protected $fillable = ['task_id', 'bitrix_message_id', 'author_id', 'is_system', 'text', 'files', 'created_at', 'updated_at'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * As in Bitrix, only the author changes a message, and never Bitrix's own
     * system lines. Not even an administrator: Bitrix would refuse their token.
     */
    public function isChangeableBy(User $user): bool
    {
        return ! $this->is_system && $this->author_id === $user->id;
    }

    /** @return array{id: int, name: string, size: int, type: string}|null */
    public function file(int $bitrixFileId): ?array
    {
        foreach ($this->files ?? [] as $file) {
            if ((int) ($file['id'] ?? 0) === $bitrixFileId) {
                return $file;
            }
        }

        return null;
    }

    protected function casts(): array
    {
        return [
            'bitrix_message_id' => 'integer',
            'is_system' => 'boolean',
            'files' => 'array',
        ];
    }
}
