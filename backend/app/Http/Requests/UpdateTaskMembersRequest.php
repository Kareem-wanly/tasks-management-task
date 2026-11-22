<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\TaskMembersDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step 4.1: both lists, in full; an empty list removes everyone from it.
 */
final class UpdateTaskMembersRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('update', $this->routeModel('task', Task::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $activeUser = Rule::exists('users', 'id')->where('is_active', true);

        return [
            'accomplice_ids' => ['present', 'array'],
            'accomplice_ids.*' => ['integer', 'distinct', $activeUser],
            'auditor_ids' => ['present', 'array'],
            'auditor_ids.*' => ['integer', 'distinct', $activeUser],
        ];
    }

    public function toDto(): TaskMembersDTO
    {
        /** @var array{accomplice_ids: list<int|string>, auditor_ids: list<int|string>} $validated */
        $validated = $this->validated();

        return TaskMembersDTO::fromArray($validated);
    }
}
