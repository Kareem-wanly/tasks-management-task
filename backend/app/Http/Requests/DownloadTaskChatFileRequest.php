<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\TaskChatMessage;
use Illuminate\Foundation\Http\FormRequest;

/** Step 4.7: a file sent in the chat; only files of that message, for whoever reads the chat. */
final class DownloadTaskChatFileRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        $message = $this->routeModel('message', TaskChatMessage::class);

        return $message->file($this->fileId()) !== null && $this->actor()->can('viewChat', $message->task);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function fileId(): int
    {
        return (int) $this->route('file');
    }
}
