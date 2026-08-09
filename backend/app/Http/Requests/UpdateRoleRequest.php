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
    return [
        'name'          => ['sometimes', 'required', 'string', 'max:255', 'unique:roles,name,' . $this->role->id],
        'display_name'  => ['nullable', 'string', 'max:255'],
        'description'   => ['nullable', 'string'],
        'permissions'   => ['nullable', 'array'],
        'permissions.*' => ['integer', 'exists:permissions,id'],
    ];
}
}