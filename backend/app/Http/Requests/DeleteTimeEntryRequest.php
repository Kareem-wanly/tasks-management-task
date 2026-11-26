<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deletes one's own time entry. */
final class DeleteTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageTimeEntry', [$this->route('entry')->task, $this->route('entry')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
