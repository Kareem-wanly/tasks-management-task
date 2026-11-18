<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Reading one task: its page, checklist or time log. */
class ShowTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('task'));
    }

    public function rules(): array
    {
        return [];
    }
}
