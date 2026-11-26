<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPagination;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\ActivityLog;
use Illuminate\Foundation\Http\FormRequest;

/** The activity log across the projects the user may see. */
final class ListActivitiesRequest extends FormRequest
{
    use HasPagination;
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', ActivityLog::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->paginationRules();
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }
}
