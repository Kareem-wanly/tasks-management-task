<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\TaskResult;
use Illuminate\Foundation\Http\FormRequest;

/** Deletes one's own result. */
final class DeleteTaskResultRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('manageResult', [$this->routeModel('result', TaskResult::class)->task, $this->routeModel('result', TaskResult::class)]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
