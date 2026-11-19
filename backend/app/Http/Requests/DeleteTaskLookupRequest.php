<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deletes one of our own statuses or priorities. */
final class DeleteTaskLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('taskStatus') ?? $this->route('taskPriority'));
    }

    public function rules(): array
    {
        return [];
    }
}
