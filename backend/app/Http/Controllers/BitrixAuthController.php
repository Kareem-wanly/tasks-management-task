<?php

namespace App\Http\Controllers;

use App\Http\Requests\BitrixAppSessionRequest;
use App\Http\Requests\ExchangeBitrixLoginCodeRequest;
use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixLoginCodes;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\BitrixUserSync;
use App\Integrations\Bitrix24\TokenManager;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Sign in with Bitrix24".
 *
 * - OAuth: our login button makes a nonce (kept in the browser's
 *   sessionStorage) → redirect() → Bitrix consent → GET callback with code +
 *   state → a one-time code in the URL fragment, bound to that nonce → the
 *   frontend trades code + nonce for a Sanctum token via exchange(). A code
 *   sent to another browser can't be traded there (login CSRF).
 * - App opened inside Bitrix: Bitrix POSTs AUTH_ID/REFRESH_ID to the callback
 *   path. Anyone can forge that POST, so it signs no one in: it sends the
 *   frame to the frontend's app page, which asks the Bitrix page around it
 *   for the current user's tokens (BX24.getAuth()) and calls appSession().
 */
class BitrixAuthController extends Controller
{
    private const STATE_KEY = 'bitrix_oauth_state';

    private const NONCE_KEY = 'bitrix_login_nonce';

    /** What Bitrix's JS SDK reads from the frame's address. */
    private const APP_FRAME_PARAMS = ['DOMAIN', 'PROTOCOL', 'LANG', 'APP_SID'];

    public function __construct(
        private readonly TokenManager $tokens,
        private readonly BitrixClient $bitrix,
        private readonly BitrixUserSync $users,
        private readonly BitrixLoginCodes $codes,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        // The state lives in the session cookie, so it must be set on the same
        // host Bitrix returns to (the public handler path), not on localhost.
        // Compare hosts only: behind ngrok Laravel sees http while the public URL is https.
        $public = rtrim((string) config('bitrix.public_url'), '/');
        $publicHost = parse_url($public, PHP_URL_HOST);
        $nonce = $request->query('nonce');
        if (! BitrixLoginCodes::isNonce($nonce)) {
            return $this->fail('invalid_state');
        }

        if ($publicHost && $request->getHttpHost() !== $publicHost.(parse_url($public, PHP_URL_PORT) ? ':'.parse_url($public, PHP_URL_PORT) : '')) {
            return redirect()->away("{$public}/auth/bitrix/redirect?".http_build_query(['nonce' => $nonce]));
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);
        $request->session()->put(self::NONCE_KEY, $nonce);

        return redirect()->away('https://'.config('bitrix.portal').'/oauth/authorize/?'.http_build_query([
            'client_id' => config('bitrix.client_id'),
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        return $request->isMethod('post')
            ? $this->toAppPage($request)
            : $this->fromOAuth($request);
    }

    private function fromOAuth(Request $request): RedirectResponse
    {
        $expected = (string) $request->session()->pull(self::STATE_KEY, '');
        $nonce = $request->session()->pull(self::NONCE_KEY);

        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state', '')) || ! BitrixLoginCodes::isNonce($nonce)) {
            return $this->fail('invalid_state');
        }

        if (! $this->isOurPortal($request->query('domain')) || ! $request->filled('code')) {
            return $this->fail('unknown_portal');
        }

        try {
            $token = $this->tokens->exchangeCode((string) $request->query('code'));
        } catch (BitrixException $e) {
            return $this->fail('exchange_failed');
        }

        // The token response names the portal it was issued for.
        if (! $this->isOurPortal($token->domain)) {
            return $this->fail('unknown_portal');
        }

        $user = $this->authenticate($token);

        return $user instanceof User
            ? redirect()->away(config('bitrix.frontend_callback_url').'#code='.$this->codes->issue($user, $nonce))
            : $this->fail($user);
    }

    /**
     * The app opened inside Bitrix. The POSTed AUTH_ID is not trusted (anyone
     * can forge this POST): the frame goes to the frontend's app page with the
     * parameters Bitrix's JS SDK needs to talk to the Bitrix page around it.
     */
    private function toAppPage(Request $request): RedirectResponse
    {
        $params = [];
        foreach (self::APP_FRAME_PARAMS as $key) {
            $value = $request->query($key) ?? $request->input($key);
            if (is_string($value) && $value !== '') {
                $params[$key] = $value;
            }
        }

        return redirect()->away(config('bitrix.frontend_app_url').'?'.http_build_query($params));
    }

    /**
     * POST /api/auth/bitrix/app-session: the tokens Bitrix's JS SDK gave the
     * app page (the Bitrix user signed in around it), for an API token in the
     * response body.
     */
    public function appSession(BitrixAppSessionRequest $request): JsonResponse
    {
        $auth = $request->frameAuth();

        if (! $this->isOurPortal($auth['domain'])) {
            return $this->refuse('unknown_portal');
        }

        // A valid token of another app on the portal is not a sign-in to ours.
        if (! $this->isOurAppToken($auth['access_token'])) {
            return $this->refuse('bitrix_rejected');
        }

        $user = $this->authenticate(new BitrixToken(
            accessToken: $auth['access_token'],
            refreshToken: $auth['refresh_token'],
            expiresAt: CarbonImmutable::now()->addSeconds($auth['expires_in']),
            domain: $auth['domain'],
            memberId: $auth['member_id'],
        ));

        return $user instanceof User
            ? response()->json(['token' => $user->createToken('bitrix_auth')->plainTextToken])
            : $this->refuse($user);
    }

    /**
     * The employee the token belongs to, with the token stored; or why not.
     * user.current doubles as the check that Bitrix accepts the token.
     */
    private function authenticate(BitrixToken $token): User|string
    {
        try {
            $profile = $this->bitrix->callWithToken($token->accessToken, 'user.current')['result'] ?? [];
        } catch (BitrixException $e) {
            return 'bitrix_rejected';
        }

        if (empty($profile['ID'])) {
            return 'bitrix_rejected';
        }

        // Never links by email here: anyone can put any email on their Bitrix profile.
        $user = $this->users->upsert($profile);

        if ($user === null) {
            return 'account_not_linked';
        }

        if (! $user->is_active) {
            return 'inactive';
        }

        $user->storeBitrixToken($token);

        return $user;
    }

    /** POST /api/auth/bitrix/exchange: the one-time code and this browser's nonce, for an API token. */
    public function exchange(ExchangeBitrixLoginCodeRequest $request): JsonResponse
    {
        $user = $this->codes->redeem($request->code(), $request->nonce());

        if ($user === null || ! $user->is_active) {
            return response()->json(['message' => 'The sign-in link expired or was already used. Please sign in again.'], 422);
        }

        return response()->json(['token' => $user->createToken('bitrix_auth')->plainTextToken]);
    }

    /** For a local app, app.info's CODE is its client_id. */
    private function isOurAppToken(string $accessToken): bool
    {
        try {
            $code = (string) ($this->bitrix->callWithToken($accessToken, 'app.info')['result']['CODE'] ?? '');
        } catch (BitrixException) {
            return false;
        }

        return hash_equals((string) config('bitrix.client_id'), $code);
    }

    private function isOurPortal(mixed $domain): bool
    {
        return is_string($domain) && $domain !== '' && hash_equals((string) config('bitrix.portal'), $domain);
    }

    private function refuse(string $reason): JsonResponse
    {
        return response()->json(['message' => 'Sign-in with Bitrix24 was refused.', 'reason' => $reason], 422);
    }

    private function fail(string $reason): RedirectResponse
    {
        return redirect()->away(config('bitrix.frontend_callback_url').'#error='.$reason);
    }
}
