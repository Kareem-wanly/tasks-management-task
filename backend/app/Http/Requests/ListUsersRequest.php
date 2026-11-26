<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\User\UserFiltersDTO;
use App\Http\Requests\Concerns\HasPagination;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** The user list, filtered by name/email and role. */
final class ListUsersRequest extends FormRequest
{
    use HasPagination;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    /** @return array<string, mixed> */
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

    public function toDto(): UserFiltersDTO
    {
        return UserFiltersDTO::fromArray($this->safe()->only(['search', 'role']));
    }
}
