<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deletes one's own time entry. */
class DeleteTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageTimeEntry', [$this->route('entry')->task, $this->route('entry')]);
    }

    public function rules(): array
    {
        return [];
    }
}
