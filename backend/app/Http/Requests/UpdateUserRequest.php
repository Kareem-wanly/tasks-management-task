<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\User\UpdateUserDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('update', $this->routeModel('user', User::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->routeModel('user', User::class)),
            ],
        ];
    }

    public function toDto(): UpdateUserDTO
    {
        return UpdateUserDTO::fromArray($this->validated());
    }
}
