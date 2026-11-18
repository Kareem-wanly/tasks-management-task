<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deletes a role (system roles are refused by the service). */
class DeleteRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('role'));
    }

    public function rules(): array
    {
        return [];
    }
}
