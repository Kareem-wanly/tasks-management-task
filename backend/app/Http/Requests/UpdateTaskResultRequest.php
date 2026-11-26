<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Rewords one's own result. */
class UpdateTaskResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $result = $this->route('result');

        return $this->user()->can('manageResult', [$result->task, $result]);
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
