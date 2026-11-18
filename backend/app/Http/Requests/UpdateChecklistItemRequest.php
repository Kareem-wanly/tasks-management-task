<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Renames a checklist item, or ticks/unticks it. */
class UpdateChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateChecklist', $this->route('item')->task);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:2000'],
            // A list is done when its items are; only items are ticked.
            'is_complete' => [Rule::prohibitedIf($this->route('item')->isList()), 'sometimes', 'boolean'],
        ];
    }

    /** @return array{title?: string, is_complete?: bool} */
    public function changes(): array
    {
        $changes = [];
        if ($this->has('title')) {
            $changes['title'] = trim($this->validated('title'));
        }
        if ($this->has('is_complete')) {
            $changes['is_complete'] = $this->boolean('is_complete');
        }

        return $changes;
    }
}
