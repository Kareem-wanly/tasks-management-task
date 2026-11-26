<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Integrations\Bitrix24\BitrixLoginCodes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Sign in with Bitrix24" (web routes): anyone may start it; Bitrix and
 * the OAuth state decide whether it succeeds.
 */
final class BitrixSignInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** Bitrix POSTs here when the app is opened inside it; OAuth comes back with a GET. */
    public function isAppOpen(): bool
    {
        return $this->isMethod('post');
    }

    /** The browser's sign-in nonce from the query; null when it is missing or malformed. */
    public function nonce(): ?string
    {
        $nonce = $this->query('nonce');

        return $this->isNonce($nonce) ? $nonce : null;
    }

    /**
     * Whether a value (e.g. the nonce kept in the session) has the shape of a sign-in nonce.
     *
     * @phpstan-assert-if-true string $value
     */
    public function isNonce(mixed $value): bool
    {
        return BitrixLoginCodes::isNonce($value);
    }

    /** A query-string value Bitrix24 sends back (state, code); '' when missing or not text. */
    public function queryText(string $key): string
    {
        $value = $this->query($key);

        return is_string($value) ? $value : '';
    }
}
