<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

/** Deletes a task (in Bitrix too). */
final class DeleteTaskRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('delete', $this->routeModel('task', Task::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
