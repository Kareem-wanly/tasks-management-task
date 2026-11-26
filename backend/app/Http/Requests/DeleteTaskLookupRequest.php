<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use Illuminate\Foundation\Http\FormRequest;

/** Deletes one of our own statuses or priorities. */
final class DeleteTaskLookupRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('delete', $this->route('taskStatus') ?? $this->route('taskPriority'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
