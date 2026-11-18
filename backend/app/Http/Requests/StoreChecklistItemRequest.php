<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Adds an item to one of the task's lists (list_id), or to its first one. */
class StoreChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateChecklist', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:2000'],
            'list_id' => ['nullable', 'integer', Rule::exists('task_checklist_items', 'id')->where('task_id', $this->route('task')->id)->whereNull('parent_id')],
        ];
    }
}
