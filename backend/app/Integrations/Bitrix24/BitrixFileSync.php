<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Models\Task;
use App\Models\TaskFile;
use App\Repositories\Contracts\TaskFileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * Step 4.5: makes our list of a task's files match Bitrix's attachments
 * (disk.attachedObject.get rows). Bitrix sends no events for attachments,
 * so this runs when the task is opened and every night.
 */
class BitrixFileSync
{
    public function __construct(
        private readonly TaskFileRepositoryInterface $files,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $attachments
     */
    public function sync(Task $task, array $attachments): void
    {
        $ours = $this->files->bitrixFiles($task);
        $users = $this->users->idsByBitrixIds(array_map(fn (array $a): int => (int) ($a['CREATED_BY'] ?? 0), $attachments));
        $seen = [];

        foreach ($attachments as $attachment) {
            $attachedId = (int) $attachment['ID'];
            $seen[] = $attachedId;
            $name = (string) ($attachment['NAME'] ?? "file-{$attachedId}");

            $file = $ours->get($attachedId) ?? new TaskFile(['task_id' => $task->id, 'attached_object_id' => $attachedId]);
            $file->fill([
                'bitrix_disk_file_id' => (int) ($attachment['OBJECT_ID'] ?? 0) ?: null,
                'uploaded_by' => $users[(int) ($attachment['CREATED_BY'] ?? 0)] ?? null,
                'name' => $name,
                'size' => (int) ($attachment['SIZE'] ?? 0),
                // Bitrix gives no type: guess it from the name, for the icon.
                'mime' => $this->mimeFor($name),
            ]);

            if (! $file->exists && isset($attachment['CREATE_TIME'])) {
                $file->created_at = \Illuminate\Support\Facades\Date::parse($attachment['CREATE_TIME'])->setTimezone(config('app.timezone'));
            }

            if (! $file->exists || $file->isDirty()) {
                $this->files->save($file);
            }
        }

        $this->files->deleteBitrixFilesExcept($task, $seen);
    }

    private function mimeFor(string $name): ?string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return $extension === '' ? null : (new MimeTypes)->getMimeTypes($extension)[0] ?? null;
    }
}
