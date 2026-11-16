<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Archives or reopens a project. */
class ToggleProjectArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('archive', $this->route('project'));
    }

    public function rules(): array
    {
        return [];
    }
}
