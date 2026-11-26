<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/** Projects for dropdowns; list filters pass include_archived=1. */
final class ProjectOptionsRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Project::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** Accepts 1, true, on, yes. */
    public function includeArchived(): bool
    {
        return $this->boolean('include_archived');
    }
}
