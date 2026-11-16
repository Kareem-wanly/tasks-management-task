<?php

namespace App\Http\Requests;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;

/** Lists every permission. */
class ListPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Permission::class);
    }

    public function rules(): array
    {
        return [];
    }
}
