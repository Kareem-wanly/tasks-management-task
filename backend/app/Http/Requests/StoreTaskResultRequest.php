<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Records what was achieved on a task as an official result. */
class StoreTaskResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addResult', $this->route('task'));
    }

    public function rules(): array
    {
        return ['text' => ['required', 'string', 'max:10000']];
    }

    public function text(): string
    {
        return trim((string) $this->validated('text'));
    }
}
