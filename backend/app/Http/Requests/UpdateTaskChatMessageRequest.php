<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\TaskChatMessageDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\TaskChatMessage;
use Illuminate\Foundation\Http\FormRequest;

/** Step 4.7: rewords one's own message (see TaskChatMessage::isChangeableBy()). */
final class UpdateTaskChatMessageRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        $message = $this->routeModel('message', TaskChatMessage::class);

        return $message->isChangeableBy($this->actor()) && $this->actor()->can('manageChatMessage', [$message->task, $message]);
    }

    /** @return array<string, mixed> */
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
