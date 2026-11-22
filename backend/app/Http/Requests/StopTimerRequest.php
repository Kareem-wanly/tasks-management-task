<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Task\StopTimerDTO;
use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

/** Stops the caller's timer; its time is logged with the optional comment. */
final class StopTimerRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('view', $this->routeModel('task', Task::class));
    }

    /** @return array<string, mixed> */
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
