<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPagination;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

/** The task list: filters, sort and page. */
class ListTasksRequest extends FormRequest
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
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => ['nullable', 'date'],
            ...$this->paginationRules(),
        ];
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return [
            'search' => $this->query('search'),
            'status' => $this->query('status'),
            // finished=true: any closing status; finished=false: any open one.
            'finished' => $this->filled('finished') ? $this->boolean('finished') : null,
            'priority' => $this->query('priority'),
            'assigned_to' => $this->query('assigned_to'),
            'project_id' => $this->query('project_id'),
            'due_date_from' => $this->query('due_date_from'),
            'due_date_to' => $this->query('due_date_to'),
            'overdue' => $this->boolean('overdue'),
            'sort_by' => $this->query('sort_by'),
            'sort_order' => $this->query('sort_order'),
        ];
    }
}
