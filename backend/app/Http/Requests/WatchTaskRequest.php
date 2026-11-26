<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Starts or stops following a task: anyone who sees it may. */
class WatchTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('task'));
    }

    public function rules(): array
    {
        return [];
    }
}
