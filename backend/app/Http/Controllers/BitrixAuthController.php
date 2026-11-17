<?php

namespace App\Http\Controllers;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\BitrixUserSync;
use App\Integrations\Bitrix24\TokenManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Sign in with Bitrix24".
 *
 * - OAuth: our login button → redirect() → Bitrix consent → GET callback with
 *   code + state.
 * - App opened inside Bitrix: Bitrix POSTs AUTH_ID/REFRESH_ID to the same
 *   handler path. Not in the plan, but it is what Bitrix sends on every open.
 *
 * Both end with a Sanctum token handed to the frontend in the URL fragment,
 * so it never reaches server logs.
 */
class BitrixAuthController extends Controller
{
    private const STATE_KEY = 'bitrix_oauth_state';

    public function __construct(
        private readonly TokenManager $tokens,
        private readonly BitrixClient $bitrix,
        private readonly BitrixUserSync $users,
    ) {}

    public function redirect(Request $request): RedirectResponse
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

        return redirect()->away('https://'.config('bitrix.portal').'/oauth/authorize/?'.http_build_query([
            'client_id' => config('bitrix.client_id'),
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        return $request->isMethod('post')
            ? $this->fromAppOpen($request)
            : $this->fromOAuth($request);
    }

    private function fromOAuth(Request $request): RedirectResponse
    {
        $expected = (string) $request->session()->pull(self::STATE_KEY, '');

        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state', ''))) {
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

        return $this->signIn($token);
    }

    private function fromAppOpen(Request $request): RedirectResponse
    {
        if (! $this->isOurPortal($request->input('DOMAIN')) || ! $request->filled(['AUTH_ID', 'REFRESH_ID'])) {
            return $this->fail('unknown_portal');
        }

        return $this->signIn(new BitrixToken(
            accessToken: (string) $request->input('AUTH_ID'),
            refreshToken: (string) $request->input('REFRESH_ID'),
            expiresAt: CarbonImmutable::now()->addSeconds((int) ($request->input('AUTH_EXPIRES') ?: 3600)),
            domain: (string) $request->input('DOMAIN'),
            memberId: $request->input('member_id'),
        ));
    }

    /**
     * user.current doubles as the check that Bitrix accepts the token.
     */
    private function signIn(BitrixToken $token): RedirectResponse
    {
        try {
            $profile = $this->bitrix->callWithToken($token->accessToken, 'user.current')['result'] ?? [];
        } catch (BitrixException $e) {
            return $this->fail('bitrix_rejected');
        }

        if (empty($profile['ID'])) {
            return $this->fail('bitrix_rejected');
        }

        // Never links by email here: anyone can put any email on their Bitrix profile.
        $user = $this->users->upsert($profile);

        if ($user === null) {
            return $this->fail('account_not_linked');
        }

        if (! $user->is_active) {
            return $this->fail('inactive');
        }

        $user->storeBitrixToken($token);

        $sanctum = $user->createToken('bitrix_auth')->plainTextToken;

        return redirect()->away(config('bitrix.frontend_callback_url').'#token='.urlencode($sanctum));
    }

    private function isOurPortal(mixed $domain): bool
    {
        return is_string($domain) && $domain !== '' && hash_equals((string) config('bitrix.portal'), $domain);
    }

    private function fail(string $reason): RedirectResponse
    {
        return redirect()->away(config('bitrix.frontend_callback_url').'#error='.$reason);
    }
}
