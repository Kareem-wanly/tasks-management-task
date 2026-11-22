<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** A user's roles. */
final class ShowUserRolesRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('viewRoles', $this->routeModel('user', User::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
