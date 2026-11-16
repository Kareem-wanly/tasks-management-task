<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Reading one project: its page, members or activity. */
class ShowProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('project'));
    }

    public function rules(): array
    {
        return [];
    }
}
