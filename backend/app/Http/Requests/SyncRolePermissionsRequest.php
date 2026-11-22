<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Role\SyncRolePermissionsDTO;
use Illuminate\Foundation\Http\FormRequest;

/** Replaces the permissions a role grants. */
final class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('role'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function toDto(): SyncRolePermissionsDTO
    {
        /** @var array{permissions: list<int|string>} $validated */
        $validated = $this->validated();

        return SyncRolePermissionsDTO::fromArray($validated);
    }
}
