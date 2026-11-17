<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Attaches a file to a task. */
class UploadTaskFileRequest extends FormRequest
{
    /**
     * Kilobytes. The file goes to Bitrix base64-encoded in the request
     * body (a third bigger), so larger files make the call too heavy.
     */
    public const MAX_KB = 20480;

    public function authorize(): bool
    {
        return $this->user()->can('manageFiles', $this->route('task'));
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:'.self::MAX_KB]];
    }

    public function messages(): array
    {
        return ['file.max' => 'Files can be up to 20 MB.'];
    }
}
