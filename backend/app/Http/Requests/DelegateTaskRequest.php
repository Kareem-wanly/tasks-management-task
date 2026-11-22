<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\DelegateTaskDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step 4.1: the responsible person hands the task to someone else.
 */
final class DelegateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delegate', $this->route('task'));
    }

    /** @return array<string, mixed> */
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

    public function toDto(): DelegateTaskDTO
    {
        /** @var array{user_id: int|string} $validated */
        $validated = $this->validated();

        return DelegateTaskDTO::fromArray($validated);
    }
}
