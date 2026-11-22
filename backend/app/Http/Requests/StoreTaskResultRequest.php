<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\TaskResultDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

/** Records what was achieved on a task as an official result. */
final class StoreTaskResultRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('addResult', $this->routeModel('task', Task::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['text' => ['required', 'string', 'max:10000']];
    }

    public function toDto(): TaskResultDTO
    {
        /** @var array{text: string} $validated */
        $validated = $this->validated();

        return TaskResultDTO::fromArray($validated);
    }
}
