<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Models\User;
use Psr\Http\Message\StreamInterface;

/**
 * A task's files in Bitrix24: Drive files attached to the task
 * (UF_TASK_WEBDAV_FILES, disk.*), through the gateway. Reads and writes
 * only; TaskFileService decides when and whether.
 */
interface BitrixFileRepositoryInterface
{
    /**
     * The task's attachments (attached object IDs), read with the service account.
     *
     * @return list<int>
     */
    public function attachedIds(int $bitrixTaskId): array;

    /**
     * The same for many tasks, in one batched request. A task Bitrix refuses
     * (e.g. deleted meanwhile) is left out.
     *
     * @param  array<int, int>  $bitrixTaskIds  Bitrix task ID by our task ID.
     * @return array<int, list<int>> Attached object IDs by our task ID.
     */
    public function attachedIdsMany(array $bitrixTaskIds): array;

    /**
     * disk.attachedObject.get rows, batched.
     *
     * @param  list<int>  $attachedIds
     * @return array<int, array<string, mixed>> By attached object ID.
     */
    public function attachments(array $attachedIds): array;

    /**
     * Uploads the file to the employee's own Drive ("Uploaded files").
     *
     * @return int The Drive file's ID.
     */
    public function uploadToDrive(User $actor, string $path, string $name): int;

    public function attach(User $actor, int $bitrixTaskId, int $diskFileId): void;

    /**
     * Replaces the whole attachment list (an empty list clears it). Bitrix
     * has no documented way to detach one file.
     *
     * @param  list<int>  $attachedIds
     */
    public function setAttachments(User $actor, int $bitrixTaskId, array $attachedIds): void;

    /**
     * Opens an attachment for download, asked for with the service account.
     * The link carries the service token, so it never leaves the server;
     * Bitrix's errors are thrown here, before the response starts.
     */
    public function openAttachment(int $attachedObjectId): StreamInterface;
}
