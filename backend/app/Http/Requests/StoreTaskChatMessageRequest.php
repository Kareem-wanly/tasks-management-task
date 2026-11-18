<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Step 4.7: a new message in the task chat. */
class StoreTaskChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('postInChat', $this->route('task'));
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
