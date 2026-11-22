<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Bitrix\BitrixSignInException;
use App\Http\Requests\BitrixAppSessionRequest;
use App\Http\Requests\BitrixSignInRequest;
use App\Http\Requests\ExchangeBitrixLoginCodeRequest;
use App\Services\Contracts\BitrixAuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * "Sign in with Bitrix24".
 *
 * - OAuth: our login button makes a nonce (kept in the browser's
 *   sessionStorage) → redirect() → Bitrix consent → GET callback with code +
 *   state → a one-time code in the URL fragment, bound to that nonce → the
 *   frontend trades code + nonce for a token through exchange().
 * - App opened inside Bitrix: Bitrix POSTs AUTH_ID/REFRESH_ID to the callback.
 *   Anyone can forge that POST, so it signs no one in: the frame goes to the
 *   frontend's app page, which signs in through appSession() with the tokens
 *   Bitrix's JS SDK gives it.
 *
 * This controller keeps only the HTTP side (session state and redirects);
 * BitrixAuthServiceInterface signs the user in.
 */
final class BitrixAuthController extends Controller
{
    private const STATE_KEY = 'bitrix_oauth_state';

    private const NONCE_KEY = 'bitrix_login_nonce';

    /** What Bitrix's JS SDK reads from the frame's address. */
    private const APP_FRAME_PARAMS = ['DOMAIN', 'PROTOCOL', 'LANG', 'APP_SID'];

    public function __construct(private readonly BitrixAuthServiceInterface $auth) {}

    public function redirect(BitrixSignInRequest $request): RedirectResponse
    {
        // The state lives in the session cookie, so it must be set on the same
        // host Bitrix returns to (the public handler path), not on localhost.
        // Compare hosts only: behind ngrok Laravel sees http while the public URL is https.
        $public = rtrim(Config::string('bitrix.public_url'), '/');
        $publicHost = parse_url($public, PHP_URL_HOST);
        $nonce = $request->nonce();
        if ($nonce === null) {
            return $this->fail('invalid_state');
        }

        if ($publicHost && $request->getHttpHost() !== $publicHost.(parse_url($public, PHP_URL_PORT) ? ':'.parse_url($public, PHP_URL_PORT) : '')) {
            return redirect()->away("{$public}/auth/bitrix/redirect?".http_build_query(['nonce' => $nonce]));
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);
        $request->session()->put(self::NONCE_KEY, $nonce);

        return redirect()->away($this->auth->authorizeUrl($state));
    }

    public function callback(BitrixSignInRequest $request): RedirectResponse
    {
        if ($request->isAppOpen()) {
            return $this->toAppPage($request);
        }

        try {
            $code = $this->fromOAuth($request);
        } catch (BitrixSignInException $e) {
            return $this->fail($e->reason);
        }

        return redirect()->away(Config::string('bitrix.frontend_callback_url').'#code='.$code);
    }

    /** POST /api/auth/bitrix/exchange: the one-time code and this browser's nonce, for an API token. */
    public function exchange(ExchangeBitrixLoginCodeRequest $request): JsonResponse
    {
        return response()->json(['token' => $this->auth->exchangeCode($request->toDto())]);
    }

    /**
     * POST /api/auth/bitrix/app-session: the tokens Bitrix's JS SDK gave the
     * app page, for an API token in the response body.
     */
    public function appSession(BitrixAppSessionRequest $request): JsonResponse
    {
        // A refusal is a BitrixSignInException, rendered as {message, reason} with 422.
        return response()->json(['token' => $this->auth->signInFromFrame($request->toDto())]);
    }

    private function fromOAuth(BitrixSignInRequest $request): string
    {
        $expected = $request->session()->pull(self::STATE_KEY);
        $nonce = $request->session()->pull(self::NONCE_KEY);

        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, $request->queryText('state')) || ! $request->isNonce($nonce)) {
            throw new BitrixSignInException('invalid_state');
        }

        return $this->auth->signInWithCode($request->queryText('code'), $request->query('domain'), $nonce);
    }

    /**
     * The app opened inside Bitrix: the POSTed AUTH_ID is not trusted. The
     * frame goes to the frontend's app page with what Bitrix's JS SDK needs.
     */
    private function toAppPage(BitrixSignInRequest $request): RedirectResponse
    {
        $params = [];
        foreach (self::APP_FRAME_PARAMS as $key) {
            $value = $request->query($key) ?? $request->input($key);
            if (is_string($value) && $value !== '') {
                $params[$key] = $value;
            }
        }

        return redirect()->away(Config::string('bitrix.frontend_app_url').'?'.http_build_query($params));
    }

    private function fail(string $reason): RedirectResponse
    {
        return redirect()->away(Config::string('bitrix.frontend_callback_url').'#error='.$reason);
    }
}
