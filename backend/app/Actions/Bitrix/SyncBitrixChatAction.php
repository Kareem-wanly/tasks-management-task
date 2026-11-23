<?php

declare(strict_types=1);

namespace App\Actions\Bitrix;

use App\Integrations\Bitrix24\BitrixPayload;
use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Repositories\Contracts\TaskChatMessageRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Step 4.7: makes our copy of a task's chat match Bitrix's
 * (im.dialog.messages.get). New messages come with OnTaskCommentAdd, but
 * edits and deletes send no events, so this also runs when the task is
 * opened (newest page) and every night (the whole chat).
 */
final readonly class SyncBitrixChatAction
{
    private const SOURCE = 'im.dialog.messages.get';

    public function __construct(
        private TaskChatMessageRepositoryInterface $messages,
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  im.dialog.messages.get "messages".
     * @param  list<array<string, mixed>>  $files  im.dialog.messages.get "files".
     * @param  bool  $complete  The rows are the whole chat; otherwise only the newest page,
     *                          and only messages from its oldest one on are compared.
     */
    public function handle(Task $task, array $rows, array $files, bool $complete): void
    {
        $rows = array_values(array_filter(
            array_map(fn (array $row): BitrixPayload => BitrixPayload::of($row, self::SOURCE), $rows),
            fn (BitrixPayload $row): bool => ! $row->equals('params.IS_DELETED', 'Y'),
        ));
        // Bitrix pages newest first; saved oldest first, so messages of the same second keep their order.
        usort($rows, fn (BitrixPayload $a, BitrixPayload $b): int => $a->int('id') <=> $b->int('id'));
        $ids = array_map(fn (BitrixPayload $row): int => $row->int('id'), $rows);
        $from = $complete || $ids === [] ? null : min($ids);

        $ours = $this->messages->bitrixMessages($task, $from);
        $authors = $this->users->idsByBitrixIds(array_map(fn (BitrixPayload $row): int => $row->intOrNull('author_id') ?? 0, $rows));
        $filesById = [];
        foreach ($files as $file) {
            $file = BitrixPayload::of($file, self::SOURCE);
            $filesById[$file->intOrNull('id') ?? 0] = $file;
        }

        foreach ($rows as $row) {
            $bitrixId = $row->int('id');
            $authorBitrixId = $row->intOrNull('author_id') ?? 0;

            $message = $ours->get($bitrixId) ?? new TaskChatMessage(['task_id' => $task->id, 'bitrix_message_id' => $bitrixId]);
            $message->fill([
                'author_id' => $authors[$authorBitrixId] ?? null,
                'is_system' => $authorBitrixId === 0,
                'text' => $this->text($row),
                'files' => $this->files($row, $filesById),
            ]);
            // Bitrix's own time, so the chat reads the same in both places.
            $message->created_at = $row->localDateOrNull('date') ?? $message->created_at ?? now();
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

    /** The text, plus the blocks Bitrix attaches to system lines (e.g. a result's summary). */
    private function text(BitrixPayload $row): string
    {
        $parts = [$row->stringOrNull('text') ?? ''];

        foreach ($row->payloads('params.ATTACH') as $attach) {
            foreach ($attach->payloads('BLOCKS') as $block) {
                // Blocks other than text (images, links...) have no MESSAGE.
                $message = $block->toArray()['MESSAGE'] ?? null;
                if (is_string($message) && $message !== '') {
                    $parts[] = $message;
                }
            }
        }

        return implode("\n", array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    /**
     * @param  array<int, BitrixPayload>  $filesById
     * @return list<array{id: int, name: string, size: int, type: string}>|null
     */
    private function files(BitrixPayload $row, array $filesById): ?array
    {
        $list = [];
        foreach ($row->ints('params.FILE_ID') as $fileId) {
            $file = $filesById[$fileId] ?? null;
            if ($file instanceof BitrixPayload) {
                $list[] = [
                    'id' => $fileId,
                    'name' => $file->stringOrNull('name') ?? 'file',
                    'size' => $file->intOrNull('size') ?? 0,
                    'type' => $file->stringOrNull('type') ?? 'file',
                ];
            }
        }

        return $list === [] ? null : $list;
    }
}
