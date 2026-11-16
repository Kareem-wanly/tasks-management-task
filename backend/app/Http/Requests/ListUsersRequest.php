<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPagination;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** The user list, filtered by name/email and role. */
class ListUsersRequest extends FormRequest
{
    use HasPagination;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            // A role name or ID.
            'role' => ['nullable', 'string', 'max:255'],
            ...$this->paginationRules(),
        ];
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }

    /** @return array{search: ?string, role: ?string} */
    public function filters(): array
    {
        return ['search' => $this->validated('search'), 'role' => $this->validated('role')];
    }
}
