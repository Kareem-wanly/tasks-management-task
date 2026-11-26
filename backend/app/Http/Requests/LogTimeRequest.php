<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasTimeEntryInput;
use Illuminate\Foundation\Http\FormRequest;

/** Logs time on a task by hand. */
final class LogTimeRequest extends FormRequest
{
    use HasTimeEntryInput;

    public function authorize(): bool
    {
        return $this->user()->can('logTime', $this->route('task'));
    }
}
