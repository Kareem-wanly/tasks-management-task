<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Task;
use App\Rules\ParentTask;
use App\Rules\ProjectMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    /**
     * Shared by POST /projects/{project}/tasks and POST /tasks (no project).
     * Choosing an assignee needs the assign permission.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        if (! $this->user()->can('create', [Task::class, $project])) {
            return false;
        }

        return ! $this->filled('assigned_to')
            || $this->user()->can('assign', [new Task(['project_id' => $project?->id]), $project]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::exists('task_statuses', 'key')],
            'priority' => ['sometimes', 'string', Rule::exists('task_priorities', 'key')],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id', new ProjectMember($this->route('project'))],
            'parent_id' => ['nullable', 'integer', new ParentTask($this->user())],
            'allow_time_tracking' => ['sometimes', 'boolean'],
            // Planned time in seconds (Bitrix TIME_ESTIMATE).
            'time_estimate' => ['sometimes', 'integer', 'min:0', 'max:3153600000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $project = $this->route('project');

            if ($project && $project->due_date && $this->filled('due_date')) {
                $taskDueDate = \Illuminate\Support\Facades\Date::parse($this->input('due_date'))->startOfDay();
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
}
