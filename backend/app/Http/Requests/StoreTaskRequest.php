<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\CreateTaskDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Project;
use App\Models\Task;
use App\Rules\ParentTask;
use App\Rules\ProjectMember;
use App\Services\Contracts\TaskServiceInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreTaskRequest extends FormRequest
{
    use ResolvesActor;

    /**
     * Shared by POST /projects/{project}/tasks and POST /tasks (no project).
     * Choosing an assignee needs the assign permission.
     */
    public function authorize(): bool
    {
        $project = $this->optionalRouteModel('project', Project::class);

        if (! $this->actor()->can('create', [Task::class, $project])) {
            return false;
        }

        return ! $this->filled('assigned_to')
            || $this->actor()->can('assign', [new Task(['project_id' => $project?->id]), $project]);
    }

    /** @return array<string, mixed> */
    public function rules(TaskServiceInterface $tasks): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::exists('task_statuses', 'key')],
            'priority' => ['sometimes', 'string', Rule::exists('task_priorities', 'key')],
            'due_date' => ['nullable', 'date'],
            // Active users only: a task without a project has no membership check (SEC-2).
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true), new ProjectMember($this->optionalRouteModel('project', Project::class))],
            'parent_id' => ['nullable', 'integer', new ParentTask($tasks, $this->actor())],
            'allow_time_tracking' => ['sometimes', 'boolean'],
            // Planned time in seconds (Bitrix TIME_ESTIMATE).
            'time_estimate' => ['sometimes', 'integer', 'min:0', 'max:3153600000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $project = $this->optionalRouteModel('project', Project::class);

            if ($project instanceof \Illuminate\Database\Eloquent\Model && $project->due_date && $this->filled('due_date')) {
                $taskDueDate = \Illuminate\Support\Facades\Date::parse($this->string('due_date')->toString())->startOfDay();
                $projectDueDate = \Illuminate\Support\Facades\Date::parse($project->due_date)->startOfDay();

                if ($taskDueDate->gt($projectDueDate)) {
                    $validator->errors()->add(
                        'due_date',
                        'The task due date cannot be later than the project due date.'
                    );
                }
            }
        });
    }

    public function toDto(): CreateTaskDTO
    {
        return CreateTaskDTO::fromArray($this->validated());
    }
}
