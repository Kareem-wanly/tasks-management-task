<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Task statuses or priorities: every signed-in user reads them. */
class ListTaskLookupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
