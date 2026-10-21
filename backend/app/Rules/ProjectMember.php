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

        $isMember = (int) $this->project->owner_id === (int) $value
            || $this->project->members()->where('users.id', $value)->exists();

        if (! $isMember) {
            $fail('The assigned user must be a member of the project.');
        }
    }
}