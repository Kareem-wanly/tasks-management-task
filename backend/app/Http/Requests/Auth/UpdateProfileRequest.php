<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\UpdateProfileDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The signed-in user's own name and email. */
final class UpdateProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->actor()->id)],
        ];
    }

    public function toDto(): UpdateProfileDTO
    {
        /** @var array{name: string, email: string} $validated */
        $validated = $this->validated();

        return UpdateProfileDTO::fromArray($validated);
    }
}
