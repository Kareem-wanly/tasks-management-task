<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\TaskLookup\SaveTaskLookupDTO;
use App\Models\TaskLookup;
use App\Services\Contracts\TaskLookupServiceInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or changing a task status or priority. A Bitrix value (is_system)
 * keeps its Bitrix link; our own values say which Bitrix value they are sent as.
 */
abstract class TaskLookupRequest extends FormRequest
{
    /** The value being changed; null when creating one. */
    abstract public function lookup(): ?TaskLookup;

    public function authorize(): bool
    {
        $lookup = $this->lookup();

        return $lookup instanceof TaskLookup ? $this->user()->can('update', $lookup) : $this->user()->can('create', $this->model());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(TaskLookup::COLORS)],
            'sort' => ['integer', 'min:0', 'max:65535'],
            'bitrix_id' => ['nullable', 'integer', Rule::in(resolve(TaskLookupServiceInterface::class)->bitrixIdsForCustomValues($this->model()))],
            ...$this->extraRules(),
        ];

        $lookup = $this->lookup();
        if (! $lookup instanceof TaskLookup) {
            return [
                'key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique((new ($this->model()))->getTable(), 'key')],
                ...$rules,
            ];
        }

        // The sync command owns a Bitrix value's link.
        if ($lookup->is_system) {
            unset($rules['bitrix_id']);
        }

        return array_map(fn (array $rule): array => ['sometimes', ...$rule], $rules);
    }

    public function toDto(): SaveTaskLookupDTO
    {
        return SaveTaskLookupDTO::fromArray($this->validated());
    }

    /** @return class-string<TaskLookup> */
    abstract protected function model(): string;

    /**
     * Rules only one kind of value has (e.g. is_final for statuses).
     *
     * @return array<string, mixed>
     */
    protected function extraRules(): array
    {
        return [];
    }
}
