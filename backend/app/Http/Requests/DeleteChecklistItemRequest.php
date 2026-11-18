<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deletes a checklist item. */
class DeleteChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateChecklist', $this->route('item')->task);
    }

    public function rules(): array
    {
        return [];
    }
}
