<?php

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Repositories\Contracts\TaskChatMessageRepositoryInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TaskChatMessageRepository implements TaskChatMessageRepositoryInterface
{
    public function latestForTask(Task $task, int $limit): Collection
    {
        return $task->chatMessages()
            ->with(['author:id,name,avatar_url', 'task'])
            ->orderByDesc('created_at')->orderByDesc('id')
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
        return $task->chatMessages()->create(['author_id' => $authorId, 'text' => $text, 'bitrix_message_id' => $bitrixMessageId]);
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

    private function bitrixQuery(Task $task, ?int $fromBitrixMessageId): HasMany
    {
        return $task->chatMessages()
            ->whereNotNull('bitrix_message_id')
            ->when($fromBitrixMessageId !== null, fn ($q) => $q->where('bitrix_message_id', '>=', $fromBitrixMessageId));
    }
}
