<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Takes a file off its task. */
class RemoveTaskFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageFiles', $this->route('file')->task);
    }

    public function rules(): array
    {
        return [];
    }
}
