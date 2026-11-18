<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

/** Lists the roles. */
class ListRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Role::class);
    }

    public function rules(): array
    {
        return [];
    }
}
