<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPagination;
use Illuminate\Foundation\Http\FormRequest;

/** A project's activity log. */
final class ListProjectActivitiesRequest extends FormRequest
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
