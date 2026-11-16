<?php

namespace App\Http\Requests;

use App\Models\TaskLookup;
use App\Services\TaskLookupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or changing a task status or priority. A Bitrix value (is_system)
 * keeps its Bitrix link; our own values say which Bitrix value they are sent as.
 */
abstract class TaskLookupRequest extends FormRequest
{
    /** @return class-string<TaskLookup> */
    abstract protected function model(): string;

    /** The value being changed; null when creating one. */
    abstract public function lookup(): ?TaskLookup;

    /** Rules only one kind of value has (e.g. is_final for statuses). */
    protected function extraRules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        $lookup = $this->lookup();

        return $lookup ? $this->user()->can('update', $lookup) : $this->user()->can('create', $this->model());
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(TaskLookup::COLORS)],
            'sort' => ['integer', 'min:0', 'max:65535'],
            'bitrix_id' => ['nullable', 'integer', Rule::in(app(TaskLookupService::class)->bitrixIdsForCustomValues($this->model()))],
            ...$this->extraRules(),
        ];

        $lookup = $this->lookup();
        if ($lookup === null) {
            return [
                'key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique((new ($this->model()))->getTable(), 'key')],
                ...$rules,
            ];
        }

        // The sync command owns a Bitrix value's link.
        if ($lookup->is_system) {
            unset($rules['bitrix_id']);
        }

        return array_map(fn (array $rule) => ['sometimes', ...$rule], $rules);
    }
}
