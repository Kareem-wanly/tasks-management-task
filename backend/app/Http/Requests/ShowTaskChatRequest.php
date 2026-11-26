<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Step 4.7: reading a task's chat. */
class ShowTaskChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewChat', $this->route('task'));
    }

    public function rules(): array
    {
        return [];
    }
}
