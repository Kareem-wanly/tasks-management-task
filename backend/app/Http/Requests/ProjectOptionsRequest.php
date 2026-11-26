<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/** Projects for dropdowns; list filters pass include_archived=1. */
class ProjectOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Project::class);
    }

    public function rules(): array
    {
        return [];
    }

    /** Accepts 1, true, on, yes. */
    public function includeArchived(): bool
    {
        return $this->boolean('include_archived');
    }
}
