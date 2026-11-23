<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Bitrix\SyncBitrixFilesAction;
use App\DTOs\Task\FileDownloadDTO;
use App\DTOs\Task\TaskFilesDTO;
use App\DTOs\Task\UploadTaskFileDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixFileRepositoryInterface;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use App\Repositories\Contracts\TaskFileRepositoryInterface;
use App\Services\Contracts\TaskFileServiceInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Step 4.5: files attached to a task. Bitrix sends no events for
 * attachments, so they are fetched from Bitrix each time the task is
 * opened. Tasks in Bitrix keep their files there (on the uploader's Drive);
 * tasks of local-only projects keep them in our storage.
 */
final readonly class TaskFileService implements TaskFileServiceInterface
{
    /** Our storage, for tasks outside Bitrix. */
    private const DISK = 'local';

    private const FOLDER = 'task-files';

    /** How long a change to a task's attachments may hold (and wait for) the lock. */
    private const FILES_LOCK_SECONDS = 15;

    public function __construct(
        private TaskFileRepositoryInterface $files,
        private BitrixFileRepositoryInterface $bitrixFiles,
        private SyncBitrixFilesAction $fileSync,
    ) {}

    /** The files, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskFilesDTO
    {
        $stale = false;

        if ($task->isInBitrix()) {
            try {
                $this->refreshFromBitrix($task);
            } catch (BitrixException $e) {
                Log::warning('Bitrix24 task files not refreshed; showing our copy.', ['task_id' => $task->id, 'error' => $e->getMessage()]);
                $stale = true;
            }
        }

        return $this->current($task, $stale);
    }

    public function upload(User $actor, Task $task, UploadTaskFileDTO $file): TaskFilesDTO
    {
        $upload = $file->file;

        if ($task->isInBitrix()) {
            $this->uploadToBitrix($actor, $task, (string) $upload->getRealPath(), $upload->getClientOriginalName());
        } else {
            $path = $upload->store(self::FOLDER."/{$task->id}", self::DISK) ?: throw new RuntimeException('The upload could not be stored.');
            $this->files->createLocal($task, $actor->id, $upload->getClientOriginalName(), (int) $upload->getSize(), $upload->getMimeType(), $path);
        }

        return $this->current($task);
    }

    /** Takes the file off the task (in Bitrix it stays on the uploader's Drive). */
    public function remove(User $actor, TaskFile $file): TaskFilesDTO
    {
        $task = $file->task;

        if ($file->isInBitrix()) {
            $this->detachInBitrix($actor, $task, $file);
        } else {
            Storage::disk(self::DISK)->delete((string) $file->path);
            $this->files->delete($file);
        }

        return $this->current($task);
    }

    public function download(TaskFile $file): FileDownloadDTO
    {
        if (! $file->isInBitrix()) {
            return new FileDownloadDTO($file->name, $file->mime, function () use ($file): void {
                fpassthru(Storage::disk(self::DISK)->readStream((string) $file->path) ?? throw new RuntimeException("File {$file->id} is missing from storage."));
            });
        }

        // Asked for with the service account: who may download is decided by our own
        // rules (whoever sees the task), so people without a Bitrix sign-in can download
        // too. Opened before the response starts, so a refusal is a normal error.
        $body = $this->bitrixFiles->openAttachment((int) $file->attached_object_id);

        return FileDownloadDTO::fromStream($file->name, $file->mime, $body);
    }

    /** Brings our list of the task's files up to date. */
    public function refreshFromBitrix(Task $task): void
    {
        $attachedIds = $this->bitrixFiles->attachedIds((int) $task->bitrix_task_id);
        $this->fileSync->handle($task, array_values($this->bitrixFiles->attachments($attachedIds)));
    }

    /**
     * The nightly pass: a chunk of tasks in batched requests. A task Bitrix
     * refuses (e.g. deleted meanwhile) is skipped, its list kept.
     *
     * @param  iterable<Task>  $tasks
     * @return int How many tasks were skipped.
     */
    public function refreshManyFromBitrix(iterable $tasks): int
    {
        $byId = [];
        foreach ($tasks as $task) {
            $byId[$task->id] = $task;
        }
        if ($byId === []) {
            return 0;
        }

        $attachedByTask = $this->bitrixFiles->attachedIdsMany(array_map(fn (Task $task): int => (int) $task->bitrix_task_id, $byId));
        $attachments = $this->bitrixFiles->attachments(array_merge(...array_values($attachedByTask ?: [[]])));

        foreach ($attachedByTask as $taskId => $attachedIds) {
            $this->fileSync->handle($byId[$taskId], array_values(array_intersect_key($attachments, array_flip($attachedIds))));
        }

        return count($byId) - count($attachedByTask);
    }

    private function current(Task $task, bool $stale = false): TaskFilesDTO
    {
        return new TaskFilesDTO($task, $this->files->forTask($task), $stale);
    }

    private function uploadToBitrix(User $actor, Task $task, string $path, string $name): void
    {
        $this->ensureSignedInWithBitrix($actor);
        $diskFileId = $this->bitrixFiles->uploadToDrive($actor, $path, $name);

        $this->whileChangingFiles($task, fn () => $this->bitrixFiles->attach($actor, (int) $task->bitrix_task_id, $diskFileId));
        $this->refreshFromBitrix($task);
    }

    /** Takes the file off the task; it stays on the uploader's Drive. */
    private function detachInBitrix(User $actor, Task $task, TaskFile $file): void
    {
        $this->ensureSignedInWithBitrix($actor);

        // Bitrix only takes the whole list, so reading and writing it happen under
        // a lock: two changes made here can't overwrite each other's file.
        $this->whileChangingFiles($task, function () use ($actor, $task, $file): void {
            $left = array_values(array_diff($this->bitrixFiles->attachedIds((int) $task->bitrix_task_id), [$file->attached_object_id]));
            $this->bitrixFiles->setAttachments($actor, (int) $task->bitrix_task_id, $left);
        });
        $this->refreshFromBitrix($task);
    }

    /** One change to a task's attachments at a time. */
    private function whileChangingFiles(Task $task, callable $change): void
    {
        try {
            Cache::lock("bitrix-task-files:{$task->id}", self::FILES_LOCK_SECONDS)->block(self::FILES_LOCK_SECONDS, $change);
        } catch (LockTimeoutException) {
            throw BitrixException::cannotSync('Another change to the files of this task is in progress. Try again in a moment.');
        }
    }

    /** Changes go to Bitrix with the employee's own token. */
    private function ensureSignedInWithBitrix(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }
}
