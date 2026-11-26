<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Role\CreateRoleDTO;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function toDto(): CreateRoleDTO
    {
        /** @var array{name: string, display_name?: ?string, description?: ?string, permissions?: list<int|string>|null} $validated */
        $validated = $this->validated();

        return CreateRoleDTO::fromArray($validated);
    }
}
