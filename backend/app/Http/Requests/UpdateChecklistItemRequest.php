<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\UpdateChecklistItemDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Renames a checklist item, or ticks/unticks it. */
final class UpdateChecklistItemRequest extends FormRequest
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

    public function toDto(): UpdateChecklistItemDTO
    {
        /** @var array{title?: string, is_complete?: bool|int|string} $validated */
        $validated = $this->validated();

        return UpdateChecklistItemDTO::fromArray($validated);
    }
}
