<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deletes a checklist item. */
final class DeleteChecklistItemRequest extends FormRequest
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
