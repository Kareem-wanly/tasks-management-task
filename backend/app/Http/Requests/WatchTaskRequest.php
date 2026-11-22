<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

/** Starts or stops following a task: anyone who sees it may. */
final class WatchTaskRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('view', $this->routeModel('task', Task::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
