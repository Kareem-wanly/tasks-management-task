<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = is_object($this->role) ? $this->role->id : $this->role;

        return [
            'name'          => [
                'sometimes', 
                'required', 
                'string', 
                'max:255', 
                Rule::unique('roles', 'name')->ignore($roleId)
            ],
            'display_name'  => ['nullable', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}