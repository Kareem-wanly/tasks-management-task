<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\TaskChatMessageDTO;
use Illuminate\Foundation\Http\FormRequest;

/** Step 4.7: a new message in the task chat. */
final class StoreTaskChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('postInChat', $this->route('task'));
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
