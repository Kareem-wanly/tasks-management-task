<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Repositories\Contracts\TaskChatMessageRepositoryInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

final class EloquentTaskChatMessageRepository implements TaskChatMessageRepositoryInterface
{
    public function latestForTask(Task $task, int $limit): Collection
    {
        return $task->chatMessages()
            ->with(['author', 'task'])->latest()->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function bitrixMessages(Task $task, ?int $fromBitrixMessageId = null): Collection
    {
        return $this->bitrixQuery($task, $fromBitrixMessageId)->get()->keyBy('bitrix_message_id');
    }

    public function save(TaskChatMessage $message): void
    {
        $message->save();
    }

    public function create(Task $task, int $authorId, string $text, ?int $bitrixMessageId = null): TaskChatMessage
    {
        if ($bitrixMessageId === null) {
            return $task->chatMessages()->create(['author_id' => $authorId, 'text' => $text]);
        }

        // A refresh (page poll or OnTaskCommentAdd) may have stored it from Bitrix already.
        return $task->chatMessages()->firstOrCreate(['bitrix_message_id' => $bitrixMessageId], ['author_id' => $authorId, 'text' => $text]);
    }

    public function update(TaskChatMessage $message, string $text): void
    {
        $message->update(['text' => $text]);
    }

    public function delete(TaskChatMessage $message): void
    {
        $message->delete();
    }

    public function deleteBitrixMessagesExcept(Task $task, array $bitrixMessageIds, ?int $fromBitrixMessageId = null): void
    {
        $this->bitrixQuery($task, $fromBitrixMessageId)->whereNotIn('bitrix_message_id', $bitrixMessageIds ?: [0])->delete();
    }

    /** @return HasMany<TaskChatMessage, Task> */
    private function bitrixQuery(Task $task, ?int $fromBitrixMessageId): HasMany
    {
        return $task->chatMessages()
            ->whereNotNull('bitrix_message_id')
            ->when($fromBitrixMessageId !== null, fn ($q) => $q->where('bitrix_message_id', '>=', $fromBitrixMessageId));
    }
}
