<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deletes one's own result. */
class DeleteTaskResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageResult', [$this->route('result')->task, $this->route('result')]);
    }

    public function rules(): array
    {
        return [];
    }
}
