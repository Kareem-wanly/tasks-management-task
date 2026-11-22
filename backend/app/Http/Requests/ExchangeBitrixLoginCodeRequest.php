<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Bitrix\ExchangeLoginCodeDTO;
use App\Integrations\Bitrix24\BitrixLoginCodes;
use Illuminate\Foundation\Http\FormRequest;

/** Trades the one-time code from a Bitrix24 sign-in, with this browser's nonce, for an API token. */
final class ExchangeBitrixLoginCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public: the code and the browser's nonce are the proof.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:'.BitrixLoginCodes::LENGTH],
            'nonce' => ['required', 'string', 'regex:'.BitrixLoginCodes::NONCE_PATTERN],
        ];
    }

    public function toDto(): ExchangeLoginCodeDTO
    {
        /** @var array{code: string, nonce: string} $validated */
        $validated = $this->validated();

        return ExchangeLoginCodeDTO::fromArray($validated);
    }
}
