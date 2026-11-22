<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Project\UpdateProjectDTO;
use App\Enums\ProjectStatus;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateProjectRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('update', $this->routeModel('project', Project::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(ProjectStatus::choosable())],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $project = $this->routeModel('project', Project::class);

            if ($this->filled('due_date')) {
                $newDueDate = \Illuminate\Support\Facades\Date::parse($this->string('due_date')->toString())->startOfDay();

                $latestUnfinishedTask = $project->tasks()
                    ->unfinished()
                    ->whereNotNull('due_date')
                    ->orderBy('due_date', 'desc')
                    ->first();

                if ($latestUnfinishedTask && $latestUnfinishedTask->due_date) {
                    $taskDueDate = \Illuminate\Support\Facades\Date::parse($latestUnfinishedTask->due_date)->startOfDay();

                    if ($newDueDate->lt($taskDueDate)) {
                        $validator->errors()->add(
                            'due_date',
                            "The project due date cannot be earlier than the due date of task '{$latestUnfinishedTask->title}' ({$taskDueDate->toDateString()})."
                        );
                    }
                }
            }
        });
    }

    public function toDto(): UpdateProjectDTO
    {
        return UpdateProjectDTO::fromArray($this->validated());
    }
}
