<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

/** One role with its permissions. */
final class ShowRoleRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('view', $this->routeModel('role', Role::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
