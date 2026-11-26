<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteTaskChatMessageRequest;
use App\Http\Requests\DownloadTaskChatFileRequest;
use App\Http\Requests\ShowTaskChatRequest;
use App\Http\Requests\StoreTaskChatMessageRequest;
use App\Http\Requests\UpdateTaskChatMessageRequest;
use App\Http\Resources\TaskChatResource;
use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Services\Contracts\TaskChatServiceInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Step 4.7: a task's chat. Every answer is the whole (newest) chat.
 */
final class TaskChatController extends Controller
{
    public function __construct(private readonly TaskChatServiceInterface $chat) {}

    public function index(ShowTaskChatRequest $request, Task $task): TaskChatResource
    {
        return new TaskChatResource($this->chat->open($task));
    }

    public function store(StoreTaskChatMessageRequest $request, Task $task): TaskChatResource
    {
        return new TaskChatResource($this->chat->send($request->actor(), $task, $request->toDto()));
    }

    public function update(UpdateTaskChatMessageRequest $request, TaskChatMessage $message): TaskChatResource
    {
        return new TaskChatResource($this->chat->update($request->actor(), $message, $request->toDto()));
    }

    public function destroy(DeleteTaskChatMessageRequest $request, TaskChatMessage $message): TaskChatResource
    {
        return new TaskChatResource($this->chat->delete($request->actor(), $message));
    }

    public function download(DownloadTaskChatFileRequest $request, TaskChatMessage $message): StreamedResponse
    {
        $download = $this->chat->download($message, $request->fileId());

        return response()->streamDownload($download->send, $download->name, ['Content-Type' => 'application/octet-stream']);
    }
}
