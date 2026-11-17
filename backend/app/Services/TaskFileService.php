<?php

namespace App\Services;

use App\Data\FileDownload;
use App\Data\TaskFiles;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixFilePusher;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use App\Repositories\Contracts\TaskFileRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Step 4.5: files attached to a task. Bitrix sends no events for
 * attachments, so they are fetched from Bitrix each time the task is
 * opened. Tasks in Bitrix keep their files there (on the uploader's Drive);
 * tasks of local-only projects keep them in our storage.
 */
class TaskFileService
{
    /** Our storage, for tasks outside Bitrix. */
    private const DISK = 'local';

    private const FOLDER = 'task-files';

    public function __construct(
        private readonly TaskFileRepositoryInterface $files,
        private readonly BitrixFilePusher $bitrix,
    ) {}

    /** The files, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskFiles
    {
        $stale = false;

        if ($this->bitrix->handles($task)) {
            try {
                $this->bitrix->refresh($task);
            } catch (BitrixException $e) {
                Log::warning('Bitrix24 task files not refreshed; showing our copy.', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                $stale = true;
            }
        }

        return $this->current($task, $stale);
    }

    public function upload(User $actor, Task $task, UploadedFile $upload): TaskFiles
    {
        if ($this->bitrix->handles($task)) {
            $this->bitrix->upload($actor, $task, $upload);
        } else {
            $path = $upload->store(self::FOLDER."/{$task->id}", self::DISK);
            $this->files->createLocal($task, $actor->id, $upload->getClientOriginalName(), (int) $upload->getSize(), $upload->getMimeType(), $path);
        }

        return $this->current($task);
    }

    /** Takes the file off the task (in Bitrix it stays on the uploader's Drive). */
    public function remove(User $actor, TaskFile $file): TaskFiles
    {
        $task = $file->task;

        if ($file->isInBitrix()) {
            $this->bitrix->detach($actor, $file);
        } else {
            Storage::disk(self::DISK)->delete((string) $file->path);
            $this->files->delete($file);
        }

        return $this->current($task);
    }

    public function download(TaskFile $file): FileDownload
    {
        if (! $file->isInBitrix()) {
            return new FileDownload($file->name, $file->mime, fn () => fpassthru(Storage::disk(self::DISK)->readStream((string) $file->path)));
        }

        // Opened before the response starts, so a refusal is a normal error.
        $body = $this->bitrix->open($this->bitrix->downloadUrl($file));

        return FileDownload::fromStream($file->name, $file->mime, $body);
    }

    private function current(Task $task, bool $stale = false): TaskFiles
    {
        return new TaskFiles($task, $this->files->forTask($task), $stale);
    }
}
