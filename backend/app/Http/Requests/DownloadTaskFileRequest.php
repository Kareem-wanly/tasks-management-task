<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Downloads a task's file: anyone who sees the task may. */
class DownloadTaskFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('file')->task);
    }

    public function rules(): array
    {
        return [];
    }
}
