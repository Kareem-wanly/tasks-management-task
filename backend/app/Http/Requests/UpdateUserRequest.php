<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\User\UpdateUserDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
        ];
    }

    public function toDto(): UpdateUserDTO
    {
        return UpdateUserDTO::fromArray($this->validated());
    }
}
