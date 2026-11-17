<?php

namespace App\Http\Requests;

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

    public function text(): string
    {
        return trim((string) $this->validated('text'));
    }
}
