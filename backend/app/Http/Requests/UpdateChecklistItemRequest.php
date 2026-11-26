<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\UpdateChecklistItemDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\TaskChecklistItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Renames a checklist item, or ticks/unticks it. */
final class UpdateChecklistItemRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('updateChecklist', $this->routeModel('item', TaskChecklistItem::class)->task);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:2000'],
            // A list is done when its items are; only items are ticked.
            'is_complete' => [Rule::prohibitedIf($this->routeModel('item', TaskChecklistItem::class)->isList()), 'sometimes', 'boolean'],
        ];
    }

    public function toDto(): UpdateChecklistItemDTO
    {
        /** @var array{title?: string, is_complete?: bool|int|string} $validated */
        $validated = $this->validated();

        return UpdateChecklistItemDTO::fromArray($validated);
    }
}
