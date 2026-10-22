<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
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
            'status' => ['sometimes', 'string', 'in:active,in_progress,on_hold,completed'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $project = $this->route('project');

            if ($project && $this->filled('due_date')) {
                $newDueDate = Carbon::parse($this->input('due_date'))->startOfDay();

                $latestUnfinishedTask = $project->tasks()
                    ->where('status', '!=', 'completed')
                    ->whereNotNull('due_date')
                    ->orderBy('due_date', 'desc')
                    ->first();

                if ($latestUnfinishedTask && $latestUnfinishedTask->due_date) {
                    $taskDueDate = Carbon::parse($latestUnfinishedTask->due_date)->startOfDay();

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
}
