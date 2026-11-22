<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\BitrixPayload;
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
        $answer = BitrixPayload::of(
            $this->bitrix->callAsService('tasks.task.get', ['taskId' => $bitrixTaskId, 'select' => ['ID', 'UF_TASK_WEBDAV_FILES']]),
            'tasks.task.get',
        );

        return $this->idsOf($answer->payload('result.task'));
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

        $batch = BitrixPayload::of($this->bitrix->batchAsService($commands), 'tasks.task.get');
        $attached = [];
        foreach (array_keys($bitrixTaskIds) as $taskId) {
            // A task Bitrix refused (e.g. deleted meanwhile) has no answer: leave it out.
            if ($batch->has("result.t{$taskId}.task")) {
                $attached[$taskId] = $this->idsOf($batch->payload("result.t{$taskId}.task"));
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
            $batch = BitrixPayload::of($this->bitrix->batchAsService($commands), 'disk.attachedObject.get');
            foreach ($chunk as $id) {
                if ($batch->has("result.a{$id}")) {
                    $attachments[$id] = $batch->payload("result.a{$id}")->toRow();
                }
            }
        }

        return $attachments;
    }

    public function uploadToDrive(User $actor, string $path, string $name): int
    {
        // Without fileContent Bitrix answers with an upload URL, and the file is
        // then sent there as multipart straight from disk (no base64 copy in memory).
        $target = BitrixPayload::of($this->bitrix->callAsUser($actor, 'disk.folder.uploadfile', [
            'id' => $this->uploadsFolderId($actor),
            'data' => ['NAME' => $name],
            // Same name as a file already there: Bitrix adds " (1)" instead of failing.
            'generateUniqueName' => true,
        ]), 'disk.folder.uploadfile')->payload('result');

        $uploadUrl = $target->stringOrNull('uploadUrl');
        $field = $target->stringOrNull('field');
        if ($uploadUrl === null || $uploadUrl === '' || $field === null || $field === '') {
            throw BitrixException::cannotSync('Bitrix24 did not give an upload link.');
        }

        $stored = BitrixPayload::of($this->bitrix->uploadFile($uploadUrl, $field, $path, $name, self::UPLOAD_TIMEOUT), 'disk upload');
        $fileId = $stored->intOrNull($stored->has('result') ? 'result.ID' : 'ID');

        return $fileId ?: throw BitrixException::cannotSync('Bitrix24 did not store the file.');
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
        $url = BitrixPayload::of($this->bitrix->callAsService('disk.attachedObject.get', ['id' => $attachedObjectId]), 'disk.attachedObject.get')
            ->stringOrNull('result.DOWNLOAD_URL');

        return $this->bitrix->openDownload($url !== null && $url !== '' ? $url : throw BitrixException::cannotSync('Bitrix24 did not return the file.'));
    }

    /** The employee's "Uploaded files" folder, or their Drive's root when Bitrix hasn't made it yet. */
    private function uploadsFolderId(User $actor): int
    {
        $storage = BitrixPayload::of($this->bitrix->callAsUser($actor, 'disk.storage.getlist', [
            'filter' => ['ENTITY_TYPE' => 'user', 'ENTITY_ID' => $actor->bitrix_user_id],
        ]), 'disk.storage.getlist')->payload('result.0');

        if ($storage->isEmpty()) {
            throw BitrixException::cannotSync('Your Bitrix24 Drive was not found.');
        }

        $children = BitrixPayload::of($this->bitrix->callAsUser($actor, 'disk.storage.getchildren', ['id' => $storage->int('ID')]), 'disk.storage.getchildren');
        foreach ($children->payloads('result') as $child) {
            if ($child->stringOrNull('CODE') === self::UPLOADS_FOLDER_CODE) {
                return $child->int('ID');
            }
        }

        return $storage->int('ROOT_OBJECT_ID');
    }

    /** @return list<int> */
    private function idsOf(BitrixPayload $task): array
    {
        return $task->ints('ufTaskWebdavFiles');
    }
}
