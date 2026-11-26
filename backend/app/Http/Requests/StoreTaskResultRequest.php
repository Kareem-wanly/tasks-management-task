<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\TaskResultDTO;
use Illuminate\Foundation\Http\FormRequest;

/** Records what was achieved on a task as an official result. */
final class StoreTaskResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addResult', $this->route('task'));
    }

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
