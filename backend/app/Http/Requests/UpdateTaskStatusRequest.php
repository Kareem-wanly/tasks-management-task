<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\ChangeTaskStatusDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::exists('task_statuses', 'key')],
        ];
    }

    public function toDto(): ChangeTaskStatusDTO
    {
        /** @var array{status: string} $validated */
        $validated = $this->validated();

        return ChangeTaskStatusDTO::fromArray($validated);
    }
}
