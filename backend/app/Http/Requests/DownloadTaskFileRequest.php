<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Downloads a task's file: anyone who sees the task may. */
final class DownloadTaskFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('file')->task);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
