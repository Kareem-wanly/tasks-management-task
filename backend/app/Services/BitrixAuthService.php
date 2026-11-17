<?php

namespace App\Services;

use App\Exceptions\BitrixSignInException;
use App\Exceptions\BusinessRuleException;
use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixLoginCodes;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\BitrixUserSync;
use App\Integrations\Bitrix24\TokenManager;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Step 1.6: "Sign in with Bitrix24", by OAuth (our login button) or when
 * the app is opened inside Bitrix. Both end with a one-time code the
 * frontend trades for a Sanctum token (exchangeCode()); an API token never
 * travels in a URL. Failures throw BitrixSignInException with a reason code.
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

    /** OAuth: the code Bitrix sent back, for our portal. Returns a one-time sign-in code. */
    public function signInWithCode(string $code, mixed $domain): string
    {
        if (! $this->isOurPortal($domain) || $code === '') {
            throw new BitrixSignInException('unknown_portal');
        }

        try {
            $token = $this->tokens->exchangeCode($code);
        } catch (BitrixException $e) {
            throw new BitrixSignInException('exchange_failed');
        }

        // The token response names the portal it was issued for.
        if (! $this->isOurPortal($token->domain)) {
            throw new BitrixSignInException('unknown_portal');
        }

        return $this->signIn($token);
    }

    /**
     * The app opened inside Bitrix: Bitrix posts AUTH_ID/REFRESH_ID.
     *
     * @param  array<string, mixed>  $params
     */
    public function signInFromAppOpen(array $params): string
    {
        if (! $this->isOurPortal($params['DOMAIN'] ?? null) || empty($params['AUTH_ID']) || empty($params['REFRESH_ID'])) {
            throw new BitrixSignInException('unknown_portal');
        }

        // A valid token of another app on the portal is not a sign-in to ours.
        if (! $this->isOurAppToken((string) $params['AUTH_ID'])) {
            throw new BitrixSignInException('bitrix_rejected');
        }

        return $this->signIn(new BitrixToken(
            accessToken: (string) $params['AUTH_ID'],
            refreshToken: (string) $params['REFRESH_ID'],
            expiresAt: CarbonImmutable::now()->addSeconds((int) (($params['AUTH_EXPIRES'] ?? null) ?: 3600)),
            domain: (string) $params['DOMAIN'],
            memberId: $params['member_id'] ?? null,
        ));
    }

    /** user.current doubles as the check that Bitrix accepts the token. */
    private function signIn(BitrixToken $token): string
    {
        try {
            $profile = $this->bitrix->callWithToken($token->accessToken, 'user.current')['result'] ?? [];
        } catch (BitrixException $e) {
            throw new BitrixSignInException('bitrix_rejected');
        }

        if (empty($profile['ID'])) {
            throw new BitrixSignInException('bitrix_rejected');
        }

        // Never links by email here: anyone can put any email on their Bitrix profile.
        $user = $this->users->upsert($profile);

        if ($user === null) {
            throw new BitrixSignInException('account_not_linked');
        }

        if (! $user->is_active) {
            throw new BitrixSignInException('inactive');
        }

        $user->storeBitrixToken($token);

        return $this->codes->issue($user);
    }

    /**
     * The one-time code from the callback, for a Sanctum token.
     *
     * @throws BusinessRuleException When the code is unknown, expired, already used or its user is inactive.
     */
    public function exchangeCode(string $code): string
    {
        $user = $this->codes->redeem($code);

        if ($user === null || ! $user->is_active) {
            throw new BusinessRuleException('The sign-in link expired or was already used. Please sign in again.');
        }

        return $this->userRepository->createApiToken($user, self::TOKEN_NAME);
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
