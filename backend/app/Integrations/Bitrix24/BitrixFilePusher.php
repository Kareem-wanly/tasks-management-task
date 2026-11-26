<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\StreamInterface;

/**
 * Step 4.5: task files in Bitrix24, with the employee's own token. A file is
 * uploaded to the employee's Drive (their "Uploaded files" folder, as Bitrix
 * does), then attached to the task. Removing only detaches it: the file
 * stays on the Drive. Only for tasks it handles(); TaskFileService keeps the
 * others' files here.
 */
class BitrixFilePusher
{
    /** The Drive folder Bitrix itself puts uploads in. */
    private const UPLOADS_FOLDER_CODE = 'FOR_UPLOADED_FILES';

    /** Seconds allowed for sending a file (base64 in the request body). */
    private const UPLOAD_TIMEOUT = 120;

    /** How long a change to a task's attachments may hold (and wait for) the lock. */
    private const FILES_LOCK_SECONDS = 15;

    public function __construct(
        private readonly BitrixGatewayInterface $bitrix,
        private readonly BitrixTaskPusher $tasks,
        private readonly BitrixFileSync $sync,
    ) {}

    /** Whether the task lives in Bitrix. */
    public function handles(Task $task): bool
    {
        return $task->bitrix_task_id !== null && $this->tasks->syncs($task);
    }

    /** Brings our list of the task's files up to date. */
    public function refresh(Task $task): void
    {
        $this->sync->sync($task, array_values($this->attachments($this->attachedIds($task))));
    }

    /**
     * The nightly pass: up to 50 tasks, in batched requests. A task Bitrix
     * refuses (e.g. deleted meanwhile) is skipped, its list kept.
     *
     * @param  iterable<Task>  $tasks
     * @return int How many tasks were skipped.
     */
    public function refreshMany(iterable $tasks): int
    {
        $commands = [];
        foreach ($tasks as $task) {
            $commands["t{$task->id}"] = ['tasks.task.get', ['taskId' => $task->bitrix_task_id, 'select' => ['ID', 'UF_TASK_WEBDAV_FILES']]];
        }
        if ($commands === []) {
            return 0;
        }

        $batch = $this->bitrix->batchAsService($commands);
        $attachedByTask = [];
        foreach ($tasks as $task) {
            $row = $batch['result']["t{$task->id}"]['task'] ?? null;
            if (is_array($row)) {
                $attachedByTask[$task->id] = array_values(array_map('intval', (array) ($row['ufTaskWebdavFiles'] ?? [])));
            }
        }

        $attachments = $this->attachments(array_merge(...array_values($attachedByTask ?: [[]])));
        $skipped = 0;
        foreach ($tasks as $task) {
            if (! isset($attachedByTask[$task->id])) {
                $skipped++;

                continue;
            }
            $this->sync->sync($task, array_values(array_intersect_key($attachments, array_flip($attachedByTask[$task->id]))));
        }

        return $skipped;
    }

    public function upload(User $actor, Task $task, UploadedFile $upload): void
    {
        $this->ensureLinked($actor);

        $name = $upload->getClientOriginalName();

        // Without fileContent Bitrix answers with an upload URL, and the file is
        // then sent there as multipart straight from disk (no base64 copy in memory).
        $target = $this->bitrix->callAsUser($actor, 'disk.folder.uploadfile', [
            'id' => $this->uploadsFolderId($actor),
            'data' => ['NAME' => $name],
            // Same name as a file already there: Bitrix adds " (1)" instead of failing.
            'generateUniqueName' => true,
        ])['result'] ?? [];

        if (empty($target['uploadUrl']) || empty($target['field'])) {
            throw BitrixException::cannotSync('Bitrix24 did not give an upload link.');
        }

        $stored = $this->bitrix->uploadFile($target['uploadUrl'], $target['field'], $upload->getRealPath(), $name, self::UPLOAD_TIMEOUT);
        $file = $stored['result'] ?? $stored;

        $diskFileId = (int) ($file['ID'] ?? 0) ?: throw BitrixException::cannotSync('Bitrix24 did not store the file.');

        $this->whileChangingFiles($task, fn (): array => $this->bitrix->callAsUser($actor, 'tasks.task.files.attach', ['taskId' => $task->bitrix_task_id, 'fileId' => $diskFileId]));
        $this->refresh($task);
    }

    /** Takes the file off the task; it stays on the uploader's Drive. */
    public function detach(User $actor, TaskFile $file): void
    {
        $this->ensureLinked($actor);
        $task = $file->task;

        // Bitrix only takes the whole list (the detach method is undocumented), so
        // reading and writing it happen under a lock: two changes made here can't
        // overwrite each other's file.
        $this->whileChangingFiles($task, fn (): array => $this->bitrix->callAsUser($actor, 'tasks.task.update', [
            'taskId' => $task->bitrix_task_id,
            // The whole list: what is left after removing this one (empty clears it).
            'fields' => ['UF_TASK_WEBDAV_FILES' => array_values(array_diff($this->attachedIds($task), [$file->attached_object_id]))],
        ]));
        $this->refresh($task);
    }

    /**
     * A download link for the file, asked for with the service account: who
     * may download is decided by our own rules (whoever sees the task), so
     * people without a Bitrix24 sign-in can download too. The link carries
     * the service token: never send it to the browser, open it with open().
     */
    public function downloadUrl(TaskFile $file): string
    {
        $url = $this->bitrix->callAsService('disk.attachedObject.get', ['id' => $file->attached_object_id])['result']['DOWNLOAD_URL'] ?? null;

        return is_string($url) && $url !== '' ? $url : throw BitrixException::cannotSync('Bitrix24 did not return the file.');
    }

    /** Opens a link from downloadUrl() before the response starts; Bitrix's errors are thrown here. */
    public function open(string $url): StreamInterface
    {
        return $this->bitrix->openDownload($url);
    }

    /**
     * disk.attachedObject.get rows, 50 per batch.
     *
     * @param  list<int>  $attachedIds
     * @return array<int, array<string, mixed>> By attached object ID.
     */
    private function attachments(array $attachedIds): array
    {
        $attachments = [];

        foreach (array_chunk(array_values(array_unique($attachedIds)), BitrixGatewayInterface::MAX_BATCH_COMMANDS) as $chunk) {
            $commands = [];
            foreach ($chunk as $id) {
                $commands["a{$id}"] = ['disk.attachedObject.get', ['id' => $id]];
            }
            $batch = $this->bitrix->batchAsService($commands);
            foreach ($chunk as $id) {
                if (is_array($row = $batch['result']["a{$id}"] ?? null)) {
                    $attachments[$id] = $row;
                }
            }
        }

        return $attachments;
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

    /** @return list<int> The task's attachments in Bitrix. */
    private function attachedIds(Task $task): array
    {
        $task = $this->bitrix->callAsService('tasks.task.get', ['taskId' => $task->bitrix_task_id, 'select' => ['ID', 'UF_TASK_WEBDAV_FILES']])['result']['task'] ?? [];

        return array_values(array_map('intval', (array) ($task['ufTaskWebdavFiles'] ?? [])));
    }

    /** The employee's "Uploaded files" folder, or their Drive's root when Bitrix hasn't made it yet. */
    private function uploadsFolderId(User $actor): int
    {
        $storage = $this->bitrix->callAsUser($actor, 'disk.storage.getlist', [
            'filter' => ['ENTITY_TYPE' => 'user', 'ENTITY_ID' => $actor->bitrix_user_id],
        ])['result'][0] ?? null;

        if (! $storage) {
            throw BitrixException::cannotSync('Your Bitrix24 Drive was not found.');
        }

        foreach ($this->bitrix->callAsUser($actor, 'disk.storage.getchildren', ['id' => $storage['ID']])['result'] ?? [] as $child) {
            if (($child['CODE'] ?? null) === self::UPLOADS_FOLDER_CODE) {
                return (int) $child['ID'];
            }
        }

        return (int) $storage['ROOT_OBJECT_ID'];
    }

    private function ensureLinked(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }
}
