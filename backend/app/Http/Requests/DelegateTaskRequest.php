<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step 4.1: the responsible person hands the task to someone else.
 */
class DelegateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delegate', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('is_active', true),
                Rule::notIn([$this->route('task')->assigned_to]),
            ],
        ];
    }

    public function messages(): array
    {
        return ['user_id.not_in' => 'The task is already assigned to this person.'];
    }
}
