<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step 4.1: both lists, in full; an empty list removes everyone from it.
 */
class UpdateTaskMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        $activeUser = Rule::exists('users', 'id')->where('is_active', true);

        return [
            'accomplice_ids' => ['present', 'array'],
            'accomplice_ids.*' => ['integer', 'distinct', $activeUser],
            'auditor_ids' => ['present', 'array'],
            'auditor_ids.*' => ['integer', 'distinct', $activeUser],
        ];
    }
}
