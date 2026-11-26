<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Project\CreateProjectDTO;
use App\Enums\ProjectStatus;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProjectRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('create', Project::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(ProjectStatus::choosable())],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function toDto(): CreateProjectDTO
    {
        return CreateProjectDTO::fromArray($this->validated());
    }
}
