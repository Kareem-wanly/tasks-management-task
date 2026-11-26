<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActor;
use App\Models\TaskFile;
use Illuminate\Foundation\Http\FormRequest;

/** Downloads a task's file: anyone who sees the task may. */
final class DownloadTaskFileRequest extends FormRequest
{
    use ResolvesActor;

    public function authorize(): bool
    {
        return $this->actor()->can('view', $this->routeModel('file', TaskFile::class)->task);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
