<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/** Removes a member from a project. */
final class RemoveProjectMemberRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('manageMembers', $this->routeModel('project', Project::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
