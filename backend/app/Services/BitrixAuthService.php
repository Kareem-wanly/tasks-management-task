<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BitrixSignInException;
use App\Exceptions\BusinessRuleException;
use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixLoginCodes;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\BitrixUserSync;
use App\Integrations\Bitrix24\TokenManager;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Step 1.6: "Sign in with Bitrix24".
 *
 * - OAuth (our login button): ends with a one-time code bound to the nonce
 *   the browser made, traded for a Sanctum token with that nonce
 *   (exchangeCode()), so a code sent to another browser is useless.
 * - The app opened inside Bitrix: the frontend sends the tokens Bitrix's JS
 *   SDK gave it (signInFromFrame()). The POST Bitrix sends on open is not
 *   trusted for sign-in: anyone can forge it.
 *
 * An API token never travels in a URL. Failures throw BitrixSignInException
 * with a reason code.
 */
class BitrixAuthService
{
    private const TOKEN_NAME = 'bitrix_auth';

    public function __construct(
        private readonly TokenManager $tokens,
        private readonly BitrixClient $bitrix,
        private readonly BitrixUserSync $users,
        private readonly UserRepositoryInterface $userRepository,
        private readonly BitrixLoginCodes $codes,
    ) {}

    /** Bitrix's consent page; $state comes back with the code. */
    public function authorizeUrl(string $state): string
    {
        return 'https://'.config('bitrix.portal').'/oauth/authorize/?'.http_build_query([
            'client_id' => config('bitrix.client_id'),
            'state' => $state,
        ]);
    }

    /** OAuth: the code Bitrix sent back, for our portal. Returns a one-time sign-in code bound to $nonce. */
    public function signInWithCode(string $code, mixed $domain, string $nonce): string
    {
        if (! $this->isOurPortal($domain) || $code === '') {
            throw new BitrixSignInException('unknown_portal');
        }

        try {
            $token = $this->tokens->exchangeCode($code);
        } catch (BitrixException) {
            throw new BitrixSignInException('exchange_failed');
        }

        // The token response names the portal it was issued for.
        if (! $this->isOurPortal($token->domain)) {
            throw new BitrixSignInException('unknown_portal');
        }

        return $this->codes->issue($this->authenticate($token), $nonce);
    }

    /**
     * The app opened inside Bitrix: the tokens Bitrix's JS SDK gave the app
     * page (the Bitrix user signed in around it). Returns a Sanctum token.
     *
     * @param  array{access_token: string, refresh_token: string, expires_in?: int|null, domain: string, member_id?: string|null}  $auth
     */
    public function signInFromFrame(array $auth): string
    {
        if (! $this->isOurPortal($auth['domain'])) {
            throw new BitrixSignInException('unknown_portal');
        }

        // A valid token of another app on the portal is not a sign-in to ours.
        if (! $this->isOurAppToken($auth['access_token'])) {
            throw new BitrixSignInException('bitrix_rejected');
        }

        $user = $this->authenticate(new BitrixToken(
            accessToken: $auth['access_token'],
            refreshToken: $auth['refresh_token'],
            expiresAt: CarbonImmutable::now()->addSeconds((int) (($auth['expires_in'] ?? null) ?: 3600)),
            domain: $auth['domain'],
            memberId: $auth['member_id'] ?? null,
        ));

        return $this->userRepository->createApiToken($user, self::TOKEN_NAME);
    }

    /**
     * The one-time code from the callback and this browser's nonce, for a Sanctum token.
     *
     * @throws BusinessRuleException When the code is unknown, expired, already used, for another browser, or its user is inactive.
     */
    public function exchangeCode(string $code, string $nonce): string
    {
        $user = $this->codes->redeem($code, $nonce);

        if (! $user instanceof User || ! $user->is_active) {
            throw new BusinessRuleException('The sign-in link expired or was already used. Please sign in again.');
        }

        return $this->userRepository->createApiToken($user, self::TOKEN_NAME);
    }

    /**
     * The employee the token belongs to, with the token stored.
     * user.current doubles as the check that Bitrix accepts the token.
     */
    private function authenticate(BitrixToken $token): User
    {
        try {
            $profile = $this->bitrix->callWithToken($token->accessToken, 'user.current')['result'] ?? [];
        } catch (BitrixException) {
            throw new BitrixSignInException('bitrix_rejected');
        }

        if (empty($profile['ID'])) {
            throw new BitrixSignInException('bitrix_rejected');
        }

        // Never links by email here: anyone can put any email on their Bitrix profile.
        $user = $this->users->upsert($profile);

        if (! $user instanceof User) {
            throw new BitrixSignInException('account_not_linked');
        }

        if (! $user->is_active) {
            throw new BitrixSignInException('inactive');
        }

        $user->storeBitrixToken($token);

        return $user;
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
}
