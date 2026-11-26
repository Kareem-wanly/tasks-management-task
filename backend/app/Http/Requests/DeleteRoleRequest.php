<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

/** Deletes a role (system roles are refused by the service). */
final class DeleteRoleRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('delete', $this->routeModel('role', Role::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
