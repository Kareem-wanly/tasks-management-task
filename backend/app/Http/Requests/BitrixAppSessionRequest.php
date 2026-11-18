<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The app opened inside Bitrix24: the frontend sends the tokens the Bitrix
 * JS SDK gave it (BX24.getAuth(), from the Bitrix page around it). They are
 * only trusted after Bitrix confirms them.
 */
class BitrixAppSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public: the Bitrix tokens are the proof, checked with Bitrix.
        return true;
    }

    public function rules(): array
    {
        return [
            'access_token' => ['required', 'string', 'max:255'],
            'refresh_token' => ['required', 'string', 'max:255'],
            'expires_in' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'domain' => ['required', 'string', 'max:255'],
            'member_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
