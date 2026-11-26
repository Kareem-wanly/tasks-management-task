<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Step 4.7: deletes one's own message (see TaskChatMessage::isChangeableBy()). */
final class DeleteTaskChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('message');

        return $message->isChangeableBy($this->user()) && $this->user()->can('manageChatMessage', [$message->task, $message]);
    }

    public function rules(): array
    {
        return [];
    }
}
