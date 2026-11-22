<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\TaskResultDTO;
use Illuminate\Foundation\Http\FormRequest;

/** Rewords one's own result. */
final class UpdateTaskResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $result = $this->route('result');

        return $this->user()->can('manageResult', [$result->task, $result]);
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
