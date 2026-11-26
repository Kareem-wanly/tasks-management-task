<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Takes a file off its task. */
final class RemoveTaskFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageFiles', $this->route('file')->task);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
