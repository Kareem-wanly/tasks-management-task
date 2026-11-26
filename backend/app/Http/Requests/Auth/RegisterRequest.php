<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\RegisterDTO;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function toDto(): RegisterDTO
    {
        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $this->validated();

        return RegisterDTO::fromArray($validated);
    }

    protected function prepareForValidation(): void
    {
        $this->offsetUnset('role');
        $this->offsetUnset('roles');
    }
}
