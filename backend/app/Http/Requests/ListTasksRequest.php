<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\TaskFiltersDTO;
use App\Http\Requests\Concerns\HasPagination;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

/** The task list: filters, sort and page. */
final class ListTasksRequest extends FormRequest
{
    use HasPagination;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Task::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],
            'assigned_to' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => ['nullable', 'date'],
            // Unknown sort columns are ignored by the repository.
            'sort_by' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'string', 'max:4'],
            ...$this->paginationRules(),
        ];
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }

    public function toDto(): TaskFiltersDTO
    {
        return TaskFiltersDTO::fromArray([
            ...$this->safe()->only(['search', 'status', 'priority', 'assigned_to', 'project_id', 'due_date_from', 'due_date_to', 'sort_by', 'sort_order']),
            // finished=true: any closing status; finished=false: any open one.
            'finished' => $this->filled('finished') ? $this->boolean('finished') : null,
            'overdue' => $this->boolean('overdue'),
        ]);
    }
}
