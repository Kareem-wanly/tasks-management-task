<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Task\FileDownloadDTO;
use App\DTOs\Task\TaskFilesDTO;
use App\DTOs\Task\UploadTaskFileDTO;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;

interface TaskFileServiceInterface
{
    /** The files, fresh from Bitrix; our last copy when Bitrix can't be reached. */
    public function open(Task $task): TaskFilesDTO;

    public function upload(User $actor, Task $task, UploadTaskFileDTO $file): TaskFilesDTO;

    /** Takes the file off the task (in Bitrix it stays on the uploader's Drive). */
    public function remove(User $actor, TaskFile $file): TaskFilesDTO;

    public function download(TaskFile $file): FileDownloadDTO;
}
