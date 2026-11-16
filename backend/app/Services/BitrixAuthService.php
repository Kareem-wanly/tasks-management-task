<?php

namespace App\Services;

use App\Exceptions\BitrixSignInException;
use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\BitrixUserSync;
use App\Integrations\Bitrix24\TokenManager;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Step 1.6: "Sign in with Bitrix24", by OAuth (our login button) or when
 * the app is opened inside Bitrix. Both end with a Sanctum token for the
 * frontend; failures throw BitrixSignInException with a reason code.
 */
class BitrixAuthService
{
    private const TOKEN_NAME = 'bitrix_auth';

    public function __construct(
        private readonly TokenManager $tokens,
        private readonly BitrixClient $bitrix,
        private readonly BitrixUserSync $users,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /** Bitrix's consent page; $state comes back with the code. */
    public function authorizeUrl(string $state): string
    {
        return 'https://'.config('bitrix.portal').'/oauth/authorize/?'.http_build_query([
            'client_id' => config('bitrix.client_id'),
            'state' => $state,
        ]);
    }

    /** OAuth: the code Bitrix sent back, for our portal. Returns a Sanctum token. */
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

        $user = $this->users->upsert($profile);

        if (! $user->is_active) {
            throw new BitrixSignInException('inactive');
        }

        $user->storeBitrixToken($token);

        return $this->userRepository->createApiToken($user, self::TOKEN_NAME);
    }

    private function isOurPortal(mixed $domain): bool
    {
        return is_string($domain) && $domain !== '' && hash_equals((string) config('bitrix.portal'), $domain);
    }
}
