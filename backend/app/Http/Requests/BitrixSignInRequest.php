<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Sign in with Bitrix24" (web routes): anyone may start it; Bitrix and
 * the OAuth state decide whether it succeeds.
 */
class BitrixSignInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    /** Bitrix POSTs here when the app is opened inside it; OAuth comes back with a GET. */
    public function isAppOpen(): bool
    {
        return $this->isMethod('post');
    }
}
