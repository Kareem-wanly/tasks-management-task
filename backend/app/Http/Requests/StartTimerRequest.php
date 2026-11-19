<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Starts the caller's timer on a task. */
final class StartTimerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('logTime', $this->route('task'));
    }

    public function rules(): array
    {
        return [];
    }
}
