<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\User\SetUserActiveDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Deactivates or activates an employee (replaces deleting). */
final class SetUserActiveRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('delete', $this->routeModel('user', User::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['active' => ['required', 'boolean']];
    }

    public function toDto(): SetUserActiveDTO
    {
        /** @var array{active: bool|int|string} $validated */
        $validated = $this->validated();

        return SetUserActiveDTO::fromArray($validated);
    }
}
