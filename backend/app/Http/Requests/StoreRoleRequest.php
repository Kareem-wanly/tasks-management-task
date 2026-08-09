<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
{
    return [
        'name'          => ['required', 'string', 'max:255', 'unique:roles,name'],
        'display_name'  => ['nullable', 'string', 'max:255'],
        'description'   => ['nullable', 'string'],
        'permissions'   => ['nullable', 'array'],
        'permissions.*' => ['integer', 'exists:permissions,id'],
    ];
}
}