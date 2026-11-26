<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Step 4.7: the task chat in Bitrix. Messages are written with the
 * employee's own token (im.message.*, which returns the new message's ID;
 * REST 3.0's tasks.task.chat.message.send does not). The service account
 * reads the chat. Only for tasks it handles(); TaskChatService keeps the
 * others' chat here.
 */
class BitrixChatPusher
{
    /** im.dialog.messages.get returns at most this many per call. */
    public const PAGE_SIZE = 50;

    public function __construct(
        private readonly BitrixGatewayInterface $bitrix,
        private readonly BitrixTaskPusher $tasks,
        private readonly BitrixChatSync $sync,
        private readonly TaskRepositoryInterface $taskRepository,
    ) {}

    /** Whether the task lives in Bitrix. */
    public function handles(Task $task): bool
    {
        return $task->bitrix_task_id !== null && $this->tasks->syncs($task);
    }

    /**
     * Brings our copy of the chat up to date: the newest page, or with
     * $complete the whole chat (catches edits and deletes of older messages).
     */
    public function refresh(Task $task, bool $complete = false): void
    {
        $chatId = $this->chatId($task);
        if ($chatId === null) {
            return;
        }

        $messages = [];
        $files = [];
        $lastId = null;

        do {
            $page = $this->bitrix->callAsService('im.dialog.messages.get', array_filter([
                'DIALOG_ID' => "chat{$chatId}",
                'LIMIT' => self::PAGE_SIZE,
                'LAST_ID' => $lastId,
            ]))['result'] ?? [];

            $rows = array_values((array) ($page['messages'] ?? []));
            $messages = [...$messages, ...$rows];
            $files = [...$files, ...array_values((array) ($page['files'] ?? []))];
            $lastId = $rows === [] ? null : min(array_map(fn (array $row): int => (int) $row['id'], $rows));
        } while ($complete && count($rows) === self::PAGE_SIZE);

        $this->sync->sync($task, $messages, $files, $complete);
    }

    /** @return int The new message's Bitrix ID. */
    public function send(User $actor, Task $task, string $text): int
    {
        $this->ensureLinked($actor);
        $chatId = $this->chatId($task) ?? throw BitrixException::cannotSync('This task has no chat in Bitrix24.');

        $id = $this->bitrix->callAsUser($actor, 'im.message.add', ['DIALOG_ID' => "chat{$chatId}", 'MESSAGE' => $text])['result'] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : throw BitrixException::cannotSync('Bitrix24 did not accept the message.');
    }

    /** Bitrix lets only the message's author change it. */
    public function update(User $actor, TaskChatMessage $message, string $text): void
    {
        $this->ensureLinked($actor);
        $this->bitrix->callAsUser($actor, 'im.message.update', ['ID' => $message->bitrix_message_id, 'MESSAGE' => $text]);
    }

    public function delete(User $actor, TaskChatMessage $message): void
    {
        $this->ensureLinked($actor);
        $this->bitrix->callAsUser($actor, 'im.message.delete', ['ID' => $message->bitrix_message_id]);
    }

    /**
     * A download link for the file, asked for with the service account: who
     * may download is decided by our own rules (whoever sees the task), so
     * people without a Bitrix24 sign-in can download too. The link carries
     * the service token: never send it to the browser, open it with open().
     */
    public function downloadUrl(int $bitrixFileId): string
    {
        $url = $this->bitrix->callAsService('disk.file.get', ['id' => $bitrixFileId])['result']['DOWNLOAD_URL'] ?? null;

        return is_string($url) && $url !== '' ? $url : throw BitrixException::cannotSync('Bitrix24 did not return the file.');
    }

    /** Opens a link from downloadUrl() before the response starts; Bitrix's errors are thrown here. */
    public function open(string $url): StreamInterface
    {
        return $this->bitrix->openDownload($url);
    }

    /** The task's chat in Bitrix, asked for once and remembered; null for tasks without one. */
    private function chatId(Task $task): ?int
    {
        if ($task->bitrix_chat_id) {
            return $task->bitrix_chat_id;
        }

        $chatId = (int) ($this->bitrix->callV3AsService('tasks.task.get', [
            'id' => $task->bitrix_task_id,
            'select' => ['id', 'chatId'],
        ])['result']['item']['chatId'] ?? 0);

        if ($chatId <= 0) {
            return null;
        }

        $this->taskRepository->setBitrixChatId($task, $chatId);

        return $chatId;
    }

    private function ensureLinked(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }
}
