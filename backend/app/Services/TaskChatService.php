<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Task\FileDownloadDTO;
use App\DTOs\Task\TaskChatDTO;
use App\DTOs\Task\TaskChatMessageDTO;
use App\Integrations\Bitrix24\BitrixChatPusher;
use App\Integrations\Bitrix24\BitrixException;
use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Models\User;
use App\Repositories\Contracts\TaskChatMessageRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.7: the task chat (replaced comments). Tasks in Bitrix write there
 * first; edits and deletes made in Bitrix send no events, so the chat is
 * also fetched when the task is opened. Tasks of local-only projects keep
 * their chat here only.
 */
class TaskChatService
{
    /** How many of the newest messages the task page shows. */
    public const SHOWN = 100;

    /** The page polls while the chat is open: ask Bitrix at most this often per task. */
    private const REFRESH_EVERY_SECONDS = 10;

    public function __construct(
        private readonly TaskChatMessageRepositoryInterface $messages,
        private readonly BitrixChatPusher $bitrix,
    ) {}

    /** The chat, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskChatDTO
    {
        $stale = false;

        if ($this->bitrix->handles($task) && Cache::add("bitrix-chat-refresh:{$task->id}", true, self::REFRESH_EVERY_SECONDS)) {
            try {
                $this->bitrix->refresh($task);
            } catch (BitrixException $e) {
                Cache::forget("bitrix-chat-refresh:{$task->id}");
                Log::warning('Bitrix24 task chat not refreshed; showing our copy.', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                $stale = true;
            }
        }

        return $this->current($task, $stale);
    }

    public function send(User $actor, Task $task, TaskChatMessageDTO $message): TaskChatDTO
    {
        $text = $message->text;
        $bitrixId = $this->bitrix->handles($task) ? $this->bitrix->send($actor, $task, $text) : null;
        $this->messages->create($task, $actor->id, $text, $bitrixId);

        return $this->current($task);
    }

    public function update(User $actor, TaskChatMessage $message, TaskChatMessageDTO $change): TaskChatDTO
    {
        $text = $change->text;

        if ($message->bitrix_message_id !== null) {
            $this->bitrix->update($actor, $message, $text);
        }
        $this->messages->update($message, $text);

        return $this->current($message->task);
    }

    public function delete(User $actor, TaskChatMessage $message): TaskChatDTO
    {
        $task = $message->task;

        if ($message->bitrix_message_id !== null) {
            $this->bitrix->delete($actor, $message);
        }
        $this->messages->delete($message);

        return $this->current($task);
    }

    /** A file sent in the chat in Bitrix (the request checks it belongs to the message). */
    public function download(TaskChatMessage $message, int $bitrixFileId): FileDownloadDTO
    {
        $file = $message->file($bitrixFileId);

        // Opened before the response starts, so a refusal is a normal error.
        $body = $this->bitrix->open($this->bitrix->downloadUrl($bitrixFileId));

        return FileDownloadDTO::fromStream((string) ($file['name'] ?? 'file'), null, $body);
    }

    private function current(Task $task, bool $stale = false): TaskChatDTO
    {
        return new TaskChatDTO($task, $this->messages->latestForTask($task, self::SHOWN), $stale);
    }
}
