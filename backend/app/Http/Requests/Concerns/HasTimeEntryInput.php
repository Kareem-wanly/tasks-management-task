<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Hours, minutes and a comment of one time entry (step 4.4).
 */
trait HasTimeEntryInput
{
    public function rules(): array
    {
        return [
            'hours' => ['required', 'integer', 'min:0', 'max:999'],
            'minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->any() && $this->seconds() === 0) {
                    $validator->errors()->add('minutes', 'Enter some time.');
                }
            },
        ];
    }

    public function seconds(): int
    {
        return (int) $this->input('hours') * 3600 + (int) $this->input('minutes') * 60;
    }

    public function comment(): ?string
    {
        $comment = trim((string) $this->input('comment', ''));

        return $comment !== '' ? $comment : null;
    }
}
