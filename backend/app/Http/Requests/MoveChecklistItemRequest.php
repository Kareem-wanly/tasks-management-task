<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Puts an item right after another item of its list (after_id), or first (null). */
class MoveChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateChecklist', $this->route('item')->task);
    }

    public function rules(): array
    {
        $item = $this->route('item');

        return [
            'after_id' => ['present', 'nullable', 'integer', Rule::exists('task_checklist_items', 'id')->where('parent_id', $item->parent_id), Rule::notIn([$item->id])],
        ];
    }
}
