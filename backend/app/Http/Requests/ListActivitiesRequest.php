<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPagination;
use App\Models\ActivityLog;
use Illuminate\Foundation\Http\FormRequest;

/** The activity log across the projects the user may see. */
class ListActivitiesRequest extends FormRequest
{
    use HasPagination;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ActivityLog::class);
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
