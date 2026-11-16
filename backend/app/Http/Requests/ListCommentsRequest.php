<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPagination;
use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;

/** A task's comments, newest first. */
class ListCommentsRequest extends FormRequest
{
    use HasPagination;

    public function authorize(): bool
    {
        $task = $this->route('task');

        return $this->user()->can('view', $task) && $this->user()->can('viewAny', [Comment::class, $task]);
    }

    public function rules(): array
    {
        return $this->paginationRules();
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }
}
