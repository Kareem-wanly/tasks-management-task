<?php

namespace App\Rules;

use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ProjectMember implements ValidationRule
{
    public function __construct(protected ?Project $project) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->project) {
            return;
        }

        if (! $this->project->hasMember($value)) {
            $fail('The assigned user must be a member of the project.');
        }
    }
}
