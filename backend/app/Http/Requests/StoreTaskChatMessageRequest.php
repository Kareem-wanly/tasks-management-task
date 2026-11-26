<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\TaskChatMessageDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

/** Step 4.7: a new message in the task chat. */
final class StoreTaskChatMessageRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('postInChat', $this->routeModel('task', Task::class));
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
