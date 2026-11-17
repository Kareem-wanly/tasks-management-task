<?php

namespace App\Integrations\Bitrix24;

use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Repositories\Contracts\TaskChatMessageRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\Carbon;

/**
 * Step 4.7: makes our copy of a task's chat match Bitrix's
 * (im.dialog.messages.get). New messages come with OnTaskCommentAdd, but
 * edits and deletes send no events, so this also runs when the task is
 * opened (newest page) and every night (the whole chat).
 */
class BitrixChatSync
{
    public function __construct(
        private readonly TaskChatMessageRepositoryInterface $messages,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  im.dialog.messages.get "messages".
     * @param  list<array<string, mixed>>  $files  im.dialog.messages.get "files".
     * @param  bool  $complete  The rows are the whole chat; otherwise only the newest page,
     *                          and only messages from its oldest one on are compared.
     */
    public function sync(Task $task, array $rows, array $files, bool $complete): void
    {
        $rows = array_values(array_filter($rows, fn (array $row) => ! $this->isDeleted($row)));
        // Bitrix pages newest first; saved oldest first, so messages of the same second keep their order.
        usort($rows, fn (array $a, array $b) => (int) $a['id'] <=> (int) $b['id']);
        $ids = array_map(fn (array $row) => (int) $row['id'], $rows);
        $from = $complete || $ids === [] ? null : min($ids);

        $ours = $this->messages->bitrixMessages($task, $from);
        $authors = $this->users->idsByBitrixIds(array_map(fn (array $row) => (int) ($row['author_id'] ?? 0), $rows));
        $filesById = [];
        foreach ($files as $file) {
            $filesById[(int) ($file['id'] ?? 0)] = $file;
        }

        foreach ($rows as $row) {
            $bitrixId = (int) $row['id'];
            $authorBitrixId = (int) ($row['author_id'] ?? 0);

            $message = $ours->get($bitrixId) ?? new TaskChatMessage(['task_id' => $task->id, 'bitrix_message_id' => $bitrixId]);
            $message->fill([
                'author_id' => $authors[$authorBitrixId] ?? null,
                'is_system' => $authorBitrixId === 0,
                'text' => $this->text($row),
                'files' => $this->files($row, $filesById),
            ]);
            // Bitrix's own time, so the chat reads the same in both places.
            $message->created_at = $this->time($row['date'] ?? null) ?? $message->created_at ?? now();
            $message->updated_at ??= $message->created_at;

            if (! $message->exists || $message->isDirty()) {
                $message->timestamps = false;
                $this->messages->save($message);
            }
        }

        if ($complete || $ids !== []) {
            $this->messages->deleteBitrixMessagesExcept($task, $ids, $from);
        }
    }

    private function isDeleted(array $row): bool
    {
        return ($row['params']['IS_DELETED'] ?? 'N') === 'Y';
    }

    /** The text, plus the blocks Bitrix attaches to system lines (e.g. a result's summary). */
    private function text(array $row): string
    {
        $parts = [(string) ($row['text'] ?? '')];

        foreach ((array) ($row['params']['ATTACH'] ?? []) as $attach) {
            foreach ((array) ($attach['BLOCKS'] ?? []) as $block) {
                if (is_string($block['MESSAGE'] ?? null) && $block['MESSAGE'] !== '') {
                    $parts[] = $block['MESSAGE'];
                }
            }
        }

        return implode("\n", array_filter($parts, fn (string $part) => $part !== ''));
    }

    /**
     * @param  array<int, array<string, mixed>>  $filesById
     * @return list<array{id: int, name: string, size: int, type: string}>|null
     */
    private function files(array $row, array $filesById): ?array
    {
        $list = [];
        foreach ((array) ($row['params']['FILE_ID'] ?? []) as $fileId) {
            $file = $filesById[(int) $fileId] ?? null;
            if ($file !== null) {
                $list[] = [
                    'id' => (int) $fileId,
                    'name' => (string) ($file['name'] ?? 'file'),
                    'size' => (int) ($file['size'] ?? 0),
                    'type' => (string) ($file['type'] ?? 'file'),
                ];
            }
        }

        return $list === [] ? null : $list;
    }

    private function time(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->setTimezone(config('app.timezone')) : null;
    }
}
