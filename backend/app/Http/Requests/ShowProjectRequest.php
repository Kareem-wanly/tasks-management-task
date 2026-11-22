<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/** Reading one project: its page, members or activity. */
final class ShowProjectRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('view', $this->routeModel('project', Project::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
