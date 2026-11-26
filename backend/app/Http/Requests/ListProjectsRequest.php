<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPagination;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/** The projects the user may open, filtered and sorted. */
class ListProjectsRequest extends FormRequest
{
    use HasPagination;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Project::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'member_id' => ['nullable', 'integer'],
            // Unknown sort columns are ignored, as before.
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'max:4'],
            ...$this->paginationRules(),
        ];
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }

    /** @return array{search: ?string, status: ?string, member_id: ?int, sort: ?string, direction: ?string} */
    public function filters(): array
    {
        $filters = $this->safe()->only(['search', 'status', 'member_id', 'sort', 'direction']);

        return [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'member_id' => isset($filters['member_id']) ? (int) $filters['member_id'] : null,
            'sort' => $filters['sort'] ?? null,
            'direction' => $filters['direction'] ?? null,
        ];
    }
}
