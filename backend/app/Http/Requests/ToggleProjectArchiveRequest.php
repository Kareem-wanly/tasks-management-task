<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/** Archives or reopens a project. */
final class ToggleProjectArchiveRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('archive', $this->routeModel('project', Project::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
