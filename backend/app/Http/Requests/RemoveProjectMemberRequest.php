<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Removes a member from a project. */
final class RemoveProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('project'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
