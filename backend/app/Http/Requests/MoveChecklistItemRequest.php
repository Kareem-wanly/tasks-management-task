<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\MoveChecklistItemDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Puts an item right after another item of its list (after_id), or first (null). */
final class MoveChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateChecklist', $this->route('item')->task);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $item = $this->route('item');

        return [
            'after_id' => ['present', 'nullable', 'integer', Rule::exists('task_checklist_items', 'id')->where('parent_id', $item->parent_id), Rule::notIn([$item->id])],
        ];
    }

    public function toDto(): MoveChecklistItemDTO
    {
        /** @var array{after_id: int|string|null} $validated */
        $validated = $this->validated();

        return MoveChecklistItemDTO::fromArray($validated);
    }
}
