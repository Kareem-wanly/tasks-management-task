<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deactivates or activates an employee (replaces deleting). */
class SetUserActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('user'));
    }

    public function rules(): array
    {
        return ['active' => ['required', 'boolean']];
    }
}
