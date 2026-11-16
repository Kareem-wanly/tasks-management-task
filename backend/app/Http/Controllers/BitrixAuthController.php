<?php

namespace App\Http\Controllers;

use App\Exceptions\BitrixSignInException;
use App\Http\Requests\BitrixSignInRequest;
use App\Services\BitrixAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * "Sign in with Bitrix24".
 *
 * - OAuth: our login button → redirect() → Bitrix consent → GET callback with
 *   code + state.
 * - App opened inside Bitrix: Bitrix POSTs AUTH_ID/REFRESH_ID to the same path.
 *
 * Both end with a Sanctum token handed to the frontend in the URL fragment,
 * so it never reaches server logs. This controller keeps only the HTTP side
 * (session state and redirects); BitrixAuthService signs the user in.
 */
class BitrixAuthController extends Controller
{
    private const STATE_KEY = 'bitrix_oauth_state';

    public function __construct(private readonly BitrixAuthService $auth) {}

    public function redirect(BitrixSignInRequest $request): RedirectResponse
    {
        // The state lives in the session cookie, so it must be set on the same
        // host Bitrix returns to (the public handler path), not on localhost.
        // Compare hosts only: behind ngrok Laravel sees http while the public URL is https.
        $public = rtrim((string) config('bitrix.public_url'), '/');
        $publicHost = parse_url($public, PHP_URL_HOST);
        if ($publicHost && $request->getHttpHost() !== $publicHost.(parse_url($public, PHP_URL_PORT) ? ':'.parse_url($public, PHP_URL_PORT) : '')) {
            return redirect()->away("{$public}/auth/bitrix/redirect");
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away($this->auth->authorizeUrl($state));
    }

    public function callback(BitrixSignInRequest $request): RedirectResponse
    {
        try {
            $token = $request->isAppOpen()
                ? $this->auth->signInFromAppOpen($request->all())
                : $this->fromOAuth($request);
        } catch (BitrixSignInException $e) {
            return redirect()->away(config('bitrix.frontend_callback_url').'#error='.$e->reason);
        }

        return redirect()->away(config('bitrix.frontend_callback_url').'#token='.urlencode($token));
    }

    private function fromOAuth(BitrixSignInRequest $request): string
    {
        $expected = (string) $request->session()->pull(self::STATE_KEY, '');

        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state', ''))) {
            throw new BitrixSignInException('invalid_state');
        }

        return $this->auth->signInWithCode((string) $request->query('code', ''), $request->query('domain'));
    }
}
