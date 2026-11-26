<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\ChangeTaskStatusDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTaskStatusRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('updateStatus', $this->routeModel('task', Task::class));
    }

    /** @return array<string, mixed> */
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
