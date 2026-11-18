<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\User\SetUserActiveDTO;
use Illuminate\Foundation\Http\FormRequest;

/** Deactivates or activates an employee (replaces deleting). */
class SetUserActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('user'));
    }

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
