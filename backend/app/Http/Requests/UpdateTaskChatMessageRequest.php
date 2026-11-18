<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\TaskChatMessageDTO;
use Illuminate\Foundation\Http\FormRequest;

/** Step 4.7: rewords one's own message (see TaskChatMessage::isChangeableBy()). */
class UpdateTaskChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('message');

        return $message->isChangeableBy($this->user()) && $this->user()->can('manageChatMessage', [$message->task, $message]);
    }

    public function rules(): array
    {
        return ['text' => ['required', 'string', 'max:5000']];
    }

    public function toDto(): TaskChatMessageDTO
    {
        /** @var array{text: string} $validated */
        $validated = $this->validated();

        return TaskChatMessageDTO::fromArray($validated);
    }
}
