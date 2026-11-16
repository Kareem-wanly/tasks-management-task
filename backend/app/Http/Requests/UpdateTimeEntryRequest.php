<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasTimeEntryInput;
use Illuminate\Foundation\Http\FormRequest;

/** Changes one's own time entry. */
class UpdateTimeEntryRequest extends FormRequest
{
    use HasTimeEntryInput;

    public function authorize(): bool
    {
        $entry = $this->route('entry');

        return $this->user()->can('manageTimeEntry', [$entry->task, $entry]);
    }
}
