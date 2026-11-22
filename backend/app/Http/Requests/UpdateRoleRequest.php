<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Role\UpdateRoleDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('role'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $roleId = is_object($this->role) ? $this->role->id : $this->role;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($roleId),
            ],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function toDto(): UpdateRoleDTO
    {
        return UpdateRoleDTO::fromArray($this->validated());
    }
}
