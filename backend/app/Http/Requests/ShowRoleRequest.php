<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** One role with its permissions. */
class ShowRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('role'));
    }

    public function rules(): array
    {
        return [];
    }
}
