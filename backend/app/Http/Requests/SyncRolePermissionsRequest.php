<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Replaces the permissions a role grants. */
class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('role'));
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
