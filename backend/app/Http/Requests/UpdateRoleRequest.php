<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Role\UpdateRoleDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('update', $this->routeModel('role', Role::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $roleId = $this->routeModel('role', Role::class)->id;

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
