<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\AddChecklistItemDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Adds an item to one of the task's lists (list_id), or to its first one. */
final class StoreChecklistItemRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('updateChecklist', $this->routeModel('task', Task::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:2000'],
            'list_id' => ['nullable', 'integer', Rule::exists('task_checklist_items', 'id')->where('task_id', $this->routeModel('task', Task::class)->id)->whereNull('parent_id')],
        ];
    }

    public function toDto(): AddChecklistItemDTO
    {
        /** @var array{title: string, list_id?: int|string|null} $validated */
        $validated = $this->validated();

        return AddChecklistItemDTO::fromArray($validated);
    }
}
