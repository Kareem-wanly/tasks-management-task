<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Task\FileDownloadDTO;
use App\DTOs\Task\TaskChatDTO;
use App\DTOs\Task\TaskChatMessageDTO;
use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Models\User;

interface TaskChatServiceInterface
{
    /** The chat, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskChatDTO;

    public function send(User $actor, Task $task, TaskChatMessageDTO $message): TaskChatDTO;

    public function update(User $actor, TaskChatMessage $message, TaskChatMessageDTO $change): TaskChatDTO;

    public function delete(User $actor, TaskChatMessage $message): TaskChatDTO;

    /** A file sent in the chat in Bitrix (the request checks it belongs to the message). */
    public function download(TaskChatMessage $message, int $bitrixFileId): FileDownloadDTO;
}
