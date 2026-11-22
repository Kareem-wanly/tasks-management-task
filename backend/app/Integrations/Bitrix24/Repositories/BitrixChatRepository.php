<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixPayload;
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
        $chatId = BitrixPayload::of($this->bitrix->callV3AsService('tasks.task.get', [
            'id' => $bitrixTaskId,
            'select' => ['id', 'chatId'],
        ]), 'tasks.task.get')->intOrNull('result.item.chatId') ?? 0;

        return $chatId > 0 ? $chatId : null;
    }

    public function messages(int $chatId, bool $complete): array
    {
        $messages = [];
        $files = [];
        $lastId = null;

        do {
            $page = BitrixPayload::of($this->bitrix->callAsService('im.dialog.messages.get', array_filter([
                'DIALOG_ID' => "chat{$chatId}",
                'LIMIT' => self::PAGE_SIZE,
                'LAST_ID' => $lastId,
            ])), 'im.dialog.messages.get');

            $rows = $page->payloads('result.messages');
            $messages = [...$messages, ...$page->rows('result.messages')];
            $files = [...$files, ...$page->rows('result.files')];
            $lastId = $rows === [] ? null : min(array_map(fn (BitrixPayload $row): int => $row->int('id'), $rows));
        } while ($complete && count($rows) === self::PAGE_SIZE);

        return ['messages' => $messages, 'files' => $files];
    }

    public function send(User $actor, int $chatId, string $text): int
    {
        $id = BitrixPayload::of($this->bitrix->callAsUser($actor, 'im.message.add', ['DIALOG_ID' => "chat{$chatId}", 'MESSAGE' => $text]), 'im.message.add')
            ->intOrNull('result');

        return $id !== null && $id > 0 ? $id : throw BitrixException::cannotSync('Bitrix24 did not accept the message.');
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
        $url = BitrixPayload::of($this->bitrix->callAsService('disk.file.get', ['id' => $bitrixFileId]), 'disk.file.get')->stringOrNull('result.DOWNLOAD_URL');

        return $this->bitrix->openDownload($url !== null && $url !== '' ? $url : throw BitrixException::cannotSync('Bitrix24 did not return the file.'));
    }
}
