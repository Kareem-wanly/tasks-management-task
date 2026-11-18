<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DTOs\Task\TimeEntryDTO;
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
            function (Validator $validator): void {
                if (! $validator->errors()->any() && $this->toDto()->seconds === 0) {
                    $validator->errors()->add('minutes', 'Enter some time.');
                }
            },
        ];
    }

    public function toDto(): TimeEntryDTO
    {
        /** @var array{hours: int|string, minutes: int|string, comment?: ?string} $input */
        $input = $this->only(['hours', 'minutes', 'comment']);

        return TimeEntryDTO::fromArray($input);
    }
}
