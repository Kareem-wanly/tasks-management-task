<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** One user with their roles. */
final class ShowUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('user'));
    }

    public function rules(): array
    {
        return [];
    }
}
