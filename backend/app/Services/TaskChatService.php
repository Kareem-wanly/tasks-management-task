<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Bitrix\SyncBitrixChatAction;
use App\DTOs\Task\FileDownloadDTO;
use App\DTOs\Task\TaskChatDTO;
use App\DTOs\Task\TaskChatMessageDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixChatRepositoryInterface;
use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Models\User;
use App\Repositories\Contracts\TaskChatMessageRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\TaskChatServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Step 4.7: the task chat (replaced comments). Tasks in Bitrix write there
 * first; edits and deletes made in Bitrix send no events, so the chat is
 * also fetched when the task is opened. Tasks of local-only projects keep
 * their chat here only.
 */
final readonly class TaskChatService implements TaskChatServiceInterface
{
    /** How many of the newest messages the task page shows. */
    public const SHOWN = 100;

    /** The page polls while the chat is open: ask Bitrix at most this often per task. */
    private const REFRESH_EVERY_SECONDS = 10;

    public function __construct(
        private TaskChatMessageRepositoryInterface $messages,
        private TaskRepositoryInterface $tasks,
        private BitrixChatRepositoryInterface $bitrixChat,
        private SyncBitrixChatAction $chatSync,
    ) {}

    /** The chat, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskChatDTO
    {
        $stale = false;

        if ($task->isInBitrix() && Cache::add("bitrix-chat-refresh:{$task->id}", true, self::REFRESH_EVERY_SECONDS)) {
            try {
                $this->refreshFromBitrix($task);
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
        $bitrixId = $task->isInBitrix() ? $this->sendToBitrix($actor, $task, $text) : null;
        $this->messages->create($task, $actor->id, $text, $bitrixId);

        return $this->current($task);
    }

    public function update(User $actor, TaskChatMessage $message, TaskChatMessageDTO $change): TaskChatDTO
    {
        $text = $change->text;

        if ($message->bitrix_message_id !== null) {
            // Bitrix lets only the message's author change it.
            $this->ensureSignedInWithBitrix($actor);
            $this->bitrixChat->update($actor, $message->bitrix_message_id, $text);
        }
        $this->messages->update($message, $text);

        return $this->current($message->task);
    }

    public function delete(User $actor, TaskChatMessage $message): TaskChatDTO
    {
        $task = $message->task;

        if ($message->bitrix_message_id !== null) {
            $this->ensureSignedInWithBitrix($actor);
            $this->bitrixChat->delete($actor, $message->bitrix_message_id);
        }
        $this->messages->delete($message);

        return $this->current($task);
    }

    /** A file sent in the chat in Bitrix (the request checks it belongs to the message). */
    public function download(TaskChatMessage $message, int $bitrixFileId): FileDownloadDTO
    {
        $file = $message->file($bitrixFileId);

        // Asked for with the service account: who may download is decided by our own
        // rules (whoever sees the task). Opened before the response starts, so a refusal
        // is a normal error.
        $body = $this->bitrixChat->openFile($bitrixFileId);

        return FileDownloadDTO::fromStream((string) ($file['name'] ?? 'file'), null, $body);
    }

    /**
     * Brings our copy of the chat up to date: the newest page, or with
     * $complete the whole chat (catches edits and deletes of older messages).
     */
    public function refreshFromBitrix(Task $task, bool $complete = false): void
    {
        $chatId = $this->chatId($task);
        if ($chatId === null) {
            return;
        }

        $page = $this->bitrixChat->messages($chatId, $complete);
        $this->chatSync->handle($task, $page['messages'], $page['files'], $complete);
    }

    private function current(Task $task, bool $stale = false): TaskChatDTO
    {
        return new TaskChatDTO($task, $this->messages->latestForTask($task, self::SHOWN), $stale);
    }

    /** @return int The new message's Bitrix ID. */
    private function sendToBitrix(User $actor, Task $task, string $text): int
    {
        $this->ensureSignedInWithBitrix($actor);
        $chatId = $this->chatId($task) ?? throw BitrixException::cannotSync('This task has no chat in Bitrix24.');

        return $this->bitrixChat->send($actor, $chatId, $text);
    }

    /** The task's chat in Bitrix, asked for once and remembered; null for tasks without one. */
    private function chatId(Task $task): ?int
    {
        if ($task->bitrix_chat_id) {
            return $task->bitrix_chat_id;
        }

        $chatId = $this->bitrixChat->chatIdOf((int) $task->bitrix_task_id);
        if ($chatId !== null) {
            $this->tasks->setBitrixChatId($task, $chatId);
        }

        return $chatId;
    }

    /** Changes go to Bitrix with the employee's own token. */
    private function ensureSignedInWithBitrix(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }
}
