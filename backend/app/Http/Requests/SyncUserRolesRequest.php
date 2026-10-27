<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        if (! $this->user()->can('manageRoles', $target)) {
            return false;
        }

        abort_if(
            $target->is($this->user()) && ! $this->user()->hasRole('Administrator'),
            403,
            'You cannot modify your own roles.'
        );

        return true;
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ];
    }
}
