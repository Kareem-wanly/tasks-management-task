<?php

declare(strict_types=1);

namespace App\Actions\Bitrix;

use App\Integrations\Bitrix24\BitrixPayload;
use App\Models\Task;
use App\Models\TaskFile;
use App\Repositories\Contracts\TaskFileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Symfony\Component\Mime\MimeTypes;

/**
 * Step 4.5: makes our list of a task's files match Bitrix's attachments
 * (disk.attachedObject.get rows). Bitrix sends no events for attachments,
 * so this runs when the task is opened and every night.
 */
final readonly class SyncBitrixFilesAction
{
    public function __construct(
        private TaskFileRepositoryInterface $files,
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $attachments
     */
    public function handle(Task $task, array $attachments): void
    {
        $ours = $this->files->bitrixFiles($task);
        $attachments = array_map(fn (array $attachment): BitrixPayload => BitrixPayload::of($attachment, 'disk.attachedObject.get'), $attachments);
        $users = $this->users->idsByBitrixIds(array_map(fn (BitrixPayload $a): int => $a->intOrNull('CREATED_BY') ?? 0, $attachments));
        $seen = [];

        foreach ($attachments as $attachment) {
            $attachedId = $attachment->int('ID');
            $seen[] = $attachedId;
            $name = $attachment->stringOrNull('NAME') ?? "file-{$attachedId}";

            $file = $ours->get($attachedId) ?? new TaskFile(['task_id' => $task->id, 'attached_object_id' => $attachedId]);
            $file->fill([
                'bitrix_disk_file_id' => $attachment->intOrNull('OBJECT_ID') ?: null,
                'uploaded_by' => $users[$attachment->intOrNull('CREATED_BY') ?? 0] ?? null,
                'name' => $name,
                'size' => $attachment->intOrNull('SIZE') ?? 0,
                // Bitrix gives no type: guess it from the name, for the icon.
                'mime' => $this->mimeFor($name),
            ]);

            if (! $file->exists && ($created = $attachment->localDateOrNull('CREATE_TIME')) instanceof CarbonImmutable) {
                $file->created_at = $created;
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
