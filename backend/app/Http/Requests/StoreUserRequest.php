<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\User\InviteUserDTO;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ];
    }

    public function toDto(): InviteUserDTO
    {
        /** @var array{name: string, email: string, password: string, role_id?: int|string|null} $validated */
        $validated = $this->validated();

        return InviteUserDTO::fromArray($validated);
    }
}
