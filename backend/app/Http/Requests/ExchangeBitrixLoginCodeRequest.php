<?php

namespace App\Http\Requests;

use App\Integrations\Bitrix24\BitrixLoginCodes;
use Illuminate\Foundation\Http\FormRequest;

/** Trades the one-time code from a Bitrix24 sign-in for an API token. */
class ExchangeBitrixLoginCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public: the code itself is the proof.
        return true;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'size:'.BitrixLoginCodes::LENGTH]];
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }
}
