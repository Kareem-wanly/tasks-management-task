<?php

namespace App\Repositories\Contracts;

use App\Models\Task;
use App\Models\TaskChatMessage;
use Illuminate\Support\Collection;

/**
 * Step 4.7: messages of task chats.
 */
interface TaskChatMessageRepositoryInterface
{
    /**
     * The newest messages, oldest first, with their authors.
     *
     * @return Collection<int, TaskChatMessage>
     */
    public function latestForTask(Task $task, int $limit): Collection;

    /**
     * The task's messages that came from Bitrix, from the given Bitrix message ID on (all when null).
     *
     * @return Collection<int, TaskChatMessage> By Bitrix message ID.
     */
    public function bitrixMessages(Task $task, ?int $fromBitrixMessageId = null): Collection;

    public function save(TaskChatMessage $message): void;

    public function create(Task $task, int $authorId, string $text, ?int $bitrixMessageId = null): TaskChatMessage;

    public function update(TaskChatMessage $message, string $text): void;

    public function delete(TaskChatMessage $message): void;

    /**
     * Deletes the task's Bitrix messages not in the list, from the given Bitrix message ID on (all when null).
     *
     * @param  list<int>  $bitrixMessageIds
     */
    public function deleteBitrixMessagesExcept(Task $task, array $bitrixMessageIds, ?int $fromBitrixMessageId = null): void;
}
