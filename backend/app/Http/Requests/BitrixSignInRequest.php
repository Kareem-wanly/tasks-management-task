<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Bitrix\BitrixCallbackDTO;
use App\Exceptions\Bitrix\BitrixSignInException;
use App\Integrations\Bitrix24\BitrixLoginCodes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Sign in with Bitrix24" (web routes): anyone may start it; Bitrix and
 * the OAuth state decide whether it succeeds.
 */
final class BitrixSignInRequest extends FormRequest
{
    /** Where the sign-in keeps its OAuth state and nonce (set by BitrixAuthController::redirect()). */
    public const STATE_KEY = 'bitrix_oauth_state';

    public const NONCE_KEY = 'bitrix_login_nonce';

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

    /**
     * The OAuth callback, once its state matches the one kept in the session
     * when the sign-in started. Both kept values are used up either way, so a
     * callback can't be replayed.
     *
     * @throws BitrixSignInException invalid_state, when they don't match.
     */
    public function toCallbackDto(): BitrixCallbackDTO
    {
        $expected = $this->session()->pull(self::STATE_KEY);
        $nonce = $this->session()->pull(self::NONCE_KEY);

        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, $this->queryText('state')) || ! $this->isNonce($nonce)) {
            throw new BitrixSignInException('invalid_state');
        }

        $domain = $this->queryText('domain');

        return BitrixCallbackDTO::fromArray(['code' => $this->queryText('code'), 'domain' => $domain === '' ? null : $domain, 'nonce' => $nonce]);
    }

    /** A query-string value Bitrix24 sends back (state, code); '' when missing or not text. */
    public function queryText(string $key): string
    {
        $value = $this->query($key);

        return is_string($value) ? $value : '';
    }
}
