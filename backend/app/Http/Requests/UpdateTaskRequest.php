<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Validation\Validator;



class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,urgent'],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $task = $this->route('task');
            $project = $task?->project;

            if ($project && $project->due_date && $this->filled('due_date')) {
                $taskDueDate = Carbon::parse($this->input('due_date'))->startOfDay();
                $projectDueDate = Carbon::parse($project->due_date)->startOfDay();

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
