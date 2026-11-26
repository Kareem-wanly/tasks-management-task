<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deletes a project (archives its Bitrix group). */
final class DeleteProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('project'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
