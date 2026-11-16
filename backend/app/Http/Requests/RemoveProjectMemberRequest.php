<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Removes a member from a project. */
class RemoveProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('project'));
    }

    public function rules(): array
    {
        return [];
    }
}
