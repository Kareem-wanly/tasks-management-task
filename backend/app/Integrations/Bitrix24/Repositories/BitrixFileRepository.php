<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixFileRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Models\User;
use Psr\Http\Message\StreamInterface;

final readonly class BitrixFileRepository implements BitrixFileRepositoryInterface
{
    /** The Drive folder Bitrix itself puts uploads in. */
    private const UPLOADS_FOLDER_CODE = 'FOR_UPLOADED_FILES';

    /** Seconds allowed for sending a file. */
    private const UPLOAD_TIMEOUT = 120;

    public function __construct(private BitrixGatewayInterface $bitrix) {}

    public function attachedIds(int $bitrixTaskId): array
    {
        $task = $this->bitrix->callAsService('tasks.task.get', ['taskId' => $bitrixTaskId, 'select' => ['ID', 'UF_TASK_WEBDAV_FILES']])['result']['task'] ?? [];

        return $this->idsOf($task);
    }

    public function attachedIdsMany(array $bitrixTaskIds): array
    {
        if ($bitrixTaskIds === []) {
            return [];
        }

        $commands = [];
        foreach ($bitrixTaskIds as $taskId => $bitrixTaskId) {
            $commands["t{$taskId}"] = ['tasks.task.get', ['taskId' => $bitrixTaskId, 'select' => ['ID', 'UF_TASK_WEBDAV_FILES']]];
        }

        $batch = $this->bitrix->batchAsService($commands);
        $attached = [];
        foreach (array_keys($bitrixTaskIds) as $taskId) {
            $row = $batch['result']["t{$taskId}"]['task'] ?? null;
            if (is_array($row)) {
                $attached[$taskId] = $this->idsOf($row);
            }
        }

        return $attached;
    }

    public function attachments(array $attachedIds): array
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

    public function uploadToDrive(User $actor, string $path, string $name): int
    {
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

        $stored = $this->bitrix->uploadFile($target['uploadUrl'], $target['field'], $path, $name, self::UPLOAD_TIMEOUT);
        $file = $stored['result'] ?? $stored;

        return (int) ($file['ID'] ?? 0) ?: throw BitrixException::cannotSync('Bitrix24 did not store the file.');
    }

    public function attach(User $actor, int $bitrixTaskId, int $diskFileId): void
    {
        $this->bitrix->callAsUser($actor, 'tasks.task.files.attach', ['taskId' => $bitrixTaskId, 'fileId' => $diskFileId]);
    }

    public function setAttachments(User $actor, int $bitrixTaskId, array $attachedIds): void
    {
        $this->bitrix->callAsUser($actor, 'tasks.task.update', [
            'taskId' => $bitrixTaskId,
            'fields' => ['UF_TASK_WEBDAV_FILES' => $attachedIds],
        ]);
    }

    public function openAttachment(int $attachedObjectId): StreamInterface
    {
        $url = $this->bitrix->callAsService('disk.attachedObject.get', ['id' => $attachedObjectId])['result']['DOWNLOAD_URL'] ?? null;

        return $this->bitrix->openDownload(is_string($url) && $url !== '' ? $url : throw BitrixException::cannotSync('Bitrix24 did not return the file.'));
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

    /**
     * @param  array<string, mixed>  $task
     * @return list<int>
     */
    private function idsOf(array $task): array
    {
        return array_values(array_map('intval', (array) ($task['ufTaskWebdavFiles'] ?? [])));
    }
}
