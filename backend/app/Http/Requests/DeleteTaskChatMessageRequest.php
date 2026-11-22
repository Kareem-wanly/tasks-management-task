<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\TaskChatMessage;
use Illuminate\Foundation\Http\FormRequest;

/** Step 4.7: deletes one's own message (see TaskChatMessage::isChangeableBy()). */
final class DeleteTaskChatMessageRequest extends FormRequest
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
        return [];
    }
}
