<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\UpdatePasswordDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use Illuminate\Foundation\Http\FormRequest;

/** The signed-in user changes their password; the current one is required. */
final class UpdatePasswordRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function toDto(): UpdatePasswordDTO
    {
        /** @var array{password: string} $validated */
        $validated = $this->validated();

        return UpdatePasswordDTO::fromArray($validated);
    }
}
