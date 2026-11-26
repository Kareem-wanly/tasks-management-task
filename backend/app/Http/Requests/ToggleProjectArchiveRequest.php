<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Archives or reopens a project. */
final class ToggleProjectArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('archive', $this->route('project'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
