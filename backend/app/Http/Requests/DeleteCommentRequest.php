<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Deletes a comment. */
class DeleteCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('comment'));
    }

    public function rules(): array
    {
        return [];
    }
}
