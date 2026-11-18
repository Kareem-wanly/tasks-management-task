<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deletes a task (in Bitrix too). */
class DeleteTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('task'));
    }

    public function rules(): array
    {
        return [];
    }
}
