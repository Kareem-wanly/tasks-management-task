<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\TaskFile;
use Illuminate\Foundation\Http\FormRequest;

/** Takes a file off its task. */
final class RemoveTaskFileRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('manageFiles', $this->routeModel('file', TaskFile::class)->task);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
