<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixChatRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Models\User;
use Psr\Http\Message\StreamInterface;

final readonly class BitrixChatRepository implements BitrixChatRepositoryInterface
{
    /** im.dialog.messages.get returns at most this many per call. */
    private const PAGE_SIZE = 50;

    public function __construct(private BitrixGatewayInterface $bitrix) {}

    public function chatIdOf(int $bitrixTaskId): ?int
    {
        $chatId = (int) ($this->bitrix->callV3AsService('tasks.task.get', [
            'id' => $bitrixTaskId,
            'select' => ['id', 'chatId'],
        ])['result']['item']['chatId'] ?? 0);

        return $chatId > 0 ? $chatId : null;
    }

    public function messages(int $chatId, bool $complete): array
    {
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

        return ['messages' => $messages, 'files' => $files];
    }

    public function send(User $actor, int $chatId, string $text): int
    {
        $id = $this->bitrix->callAsUser($actor, 'im.message.add', ['DIALOG_ID' => "chat{$chatId}", 'MESSAGE' => $text])['result'] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : throw BitrixException::cannotSync('Bitrix24 did not accept the message.');
    }

    public function update(User $actor, int $bitrixMessageId, string $text): void
    {
        $this->bitrix->callAsUser($actor, 'im.message.update', ['ID' => $bitrixMessageId, 'MESSAGE' => $text]);
    }

    public function delete(User $actor, int $bitrixMessageId): void
    {
        $this->bitrix->callAsUser($actor, 'im.message.delete', ['ID' => $bitrixMessageId]);
    }

    public function openFile(int $bitrixFileId): StreamInterface
    {
        $url = $this->bitrix->callAsService('disk.file.get', ['id' => $bitrixFileId])['result']['DOWNLOAD_URL'] ?? null;

        return $this->bitrix->openDownload(is_string($url) && $url !== '' ? $url : throw BitrixException::cannotSync('Bitrix24 did not return the file.'));
    }
}
