<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A user's roles. */
final class ShowUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewRoles', $this->route('user'));
    }

    public function rules(): array
    {
        return [];
    }
}
