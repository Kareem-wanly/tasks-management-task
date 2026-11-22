<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

/** Lists the roles. */
final class ListRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Role::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
