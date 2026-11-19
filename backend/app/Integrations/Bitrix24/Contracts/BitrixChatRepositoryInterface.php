<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Models\User;
use Psr\Http\Message\StreamInterface;

/**
 * A task's chat in Bitrix24 (im.*), through the gateway. Reads and writes
 * only; TaskChatService decides when and whether.
 */
interface BitrixChatRepositoryInterface
{
    /** The task's chat ID in Bitrix, read with the service account; null when it has none. */
    public function chatIdOf(int $bitrixTaskId): ?int;

    /**
     * The newest page of messages, or with $complete every message (older pages too).
     *
     * @return array{messages: list<array<string, mixed>>, files: list<array<string, mixed>>}
     */
    public function messages(int $chatId, bool $complete): array;

    /** @return int The new message's Bitrix ID. */
    public function send(User $actor, int $chatId, string $text): int;

    /** Bitrix lets only the message's author change it. */
    public function update(User $actor, int $bitrixMessageId, string $text): void;

    public function delete(User $actor, int $bitrixMessageId): void;

    /**
     * Opens a chat file for download, asked for with the service account. The
     * link carries the service token, so it never leaves the server; Bitrix's
     * errors are thrown here, before the response starts.
     */
    public function openFile(int $bitrixFileId): StreamInterface;
}
