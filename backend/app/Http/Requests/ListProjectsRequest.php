<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Project\ProjectFiltersDTO;
use App\Http\Requests\Concerns\HasPagination;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/** The projects the user may open, filtered and sorted. */
final class ListProjectsRequest extends FormRequest
{
    use HasPagination;
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Project::class);
    }

    /** @return array<string, mixed> */
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

    public function toDto(): ProjectFiltersDTO
    {
        return ProjectFiltersDTO::fromArray($this->safe()->only(['search', 'status', 'member_id', 'sort', 'direction']));
    }
}
