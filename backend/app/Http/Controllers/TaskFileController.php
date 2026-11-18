<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DownloadTaskFileRequest;
use App\Http\Requests\RemoveTaskFileRequest;
use App\Http\Requests\ShowTaskRequest;
use App\Http\Requests\UploadTaskFileRequest;
use App\Http\Resources\TaskFilesResource;
use App\Models\Task;
use App\Models\TaskFile;
use App\Services\TaskFileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Step 4.5: files attached to a task. Every write answers with the whole list.
 */
class TaskFileController extends Controller
{
    public function __construct(private readonly TaskFileService $files) {}

    public function index(ShowTaskRequest $request, Task $task): TaskFilesResource
    {
        return new TaskFilesResource($this->files->open($task));
    }

    public function store(UploadTaskFileRequest $request, Task $task): TaskFilesResource
    {
        return new TaskFilesResource($this->files->upload($request->user(), $task, $request->toDto()));
    }

    public function destroy(RemoveTaskFileRequest $request, TaskFile $file): TaskFilesResource
    {
        return new TaskFilesResource($this->files->remove($request->user(), $file));
    }

    public function download(DownloadTaskFileRequest $request, TaskFile $file): StreamedResponse
    {
        $download = $this->files->download($file);

        return response()->streamDownload($download->send, $download->name, ['Content-Type' => $download->mime ?? 'application/octet-stream']);
    }
}
