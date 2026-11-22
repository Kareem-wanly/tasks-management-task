<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Reading one task: its page, checklist or time log. */
final class ShowTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('task'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
