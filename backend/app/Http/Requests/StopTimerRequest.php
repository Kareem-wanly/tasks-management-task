<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\StopTimerDTO;
use Illuminate\Foundation\Http\FormRequest;

/** Stops the caller's timer; its time is logged with the optional comment. */
final class StopTimerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('task'));
    }

    public function rules(): array
    {
        return ['comment' => ['nullable', 'string', 'max:2000']];
    }

    public function toDto(): StopTimerDTO
    {
        /** @var array{comment?: ?string} $validated */
        $validated = $this->validated();

        return StopTimerDTO::fromArray($validated);
    }
}
