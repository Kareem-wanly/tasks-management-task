<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Integrations\Bitrix24\BitrixLoginCodes;
use Illuminate\Foundation\Http\FormRequest;

/** Trades the one-time code from a Bitrix24 sign-in, with this browser's nonce, for an API token. */
class ExchangeBitrixLoginCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public: the code and the browser's nonce are the proof.
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:'.BitrixLoginCodes::LENGTH],
            'nonce' => ['required', 'string', 'regex:'.BitrixLoginCodes::NONCE_PATTERN],
        ];
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }

    public function nonce(): string
    {
        return (string) $this->validated('nonce');
    }
}
