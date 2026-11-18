<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPagination;
use Illuminate\Foundation\Http\FormRequest;

/** One project's tasks. */
class ListProjectTasksRequest extends FormRequest
{
    use HasPagination;

    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('project'));
    }

    public function rules(): array
    {
        return $this->paginationRules();
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }
}
