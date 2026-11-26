<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\UpdateTaskDTO;
use App\Models\Task;
use App\Rules\ParentTask;
use App\Rules\ProjectMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateTaskRequest extends FormRequest
{
    /** Changing the assignee also needs the assign permission. */
    public function authorize(): bool
    {
        $task = $this->route('task');

        if (! $this->user()->can('update', $task)) {
            return false;
        }

        return ! $this->changesAssignee($task) || $this->user()->can('assign', $task);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::exists('task_statuses', 'key')],
            'priority' => ['sometimes', 'string', Rule::exists('task_priorities', 'key')],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id', new ProjectMember($this->route('task')?->project)],
            'parent_id' => ['nullable', 'integer', new ParentTask($this->user(), $this->route('task'))],
            'allow_time_tracking' => ['sometimes', 'boolean'],
            // Planned time in seconds (Bitrix TIME_ESTIMATE).
            'time_estimate' => ['sometimes', 'integer', 'min:0', 'max:3153600000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $task = $this->route('task');
            $project = $task?->project;

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

    public function toDto(): UpdateTaskDTO
    {
        return UpdateTaskDTO::fromArray($this->validated());
    }

    private function changesAssignee(Task $task): bool
    {
        if (! $this->has('assigned_to')) {
            return false;
        }

        $assignee = $this->input('assigned_to');

        return $assignee === null ? $task->assigned_to !== null : (int) $assignee !== $task->assigned_to;
    }
}
