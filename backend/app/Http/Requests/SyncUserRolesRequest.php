<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\User\SyncUserRolesDTO;
use App\Enums\SystemRole;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class SyncUserRolesRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        $target = $this->routeModel('user', User::class);

        if (! $this->actor()->can('manageRoles', $target)) {
            return false;
        }

        abort_if(
            $target->is($this->actor()) && ! $this->actor()->hasRole(SystemRole::Administrator),
            403,
            'You cannot modify your own roles.'
        );

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ];
    }

    public function toDto(): SyncUserRolesDTO
    {
        /** @var array{roles: list<int|string>} $validated */
        $validated = $this->validated();

        return SyncUserRolesDTO::fromArray($validated);
    }
}
