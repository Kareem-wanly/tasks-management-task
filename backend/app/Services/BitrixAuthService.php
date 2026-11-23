<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Bitrix\SyncBitrixUserAction;
use App\DTOs\Bitrix\BitrixFrameAuthDTO;
use App\DTOs\Bitrix\ExchangeLoginCodeDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\Bitrix\BitrixLoginCodeExpiredException;
use App\Exceptions\Bitrix\BitrixSignInException;
use App\Integrations\Bitrix24\BitrixLoginCodes;
use App\Integrations\Bitrix24\BitrixPayload;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\BitrixAppRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixOAuthGatewayInterface;
use App\Models\User;
use App\Repositories\Contracts\BitrixTokenRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\BitrixAuthServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;

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
final readonly class BitrixAuthService implements BitrixAuthServiceInterface
{
    private const TOKEN_NAME = 'bitrix_auth';

    public function __construct(
        private BitrixOAuthGatewayInterface $oauth,
        private BitrixTokenRepositoryInterface $tokens,
        private BitrixAppRepositoryInterface $bitrixApp,
        private SyncBitrixUserAction $syncUser,
        private ConnectionInterface $database,
        private UserRepositoryInterface $userRepository,
        private BitrixLoginCodes $codes,
    ) {}

    /** Bitrix's consent page; $state comes back with the code. */
    public function authorizeUrl(string $state): string
    {
        return 'https://'.Config::string('bitrix.portal').'/oauth/authorize/?'.http_build_query([
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
            $token = $this->oauth->exchangeCode($code);
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
     */
    public function signInFromFrame(BitrixFrameAuthDTO $auth): string
    {
        if (! $this->isOurPortal($auth->domain)) {
            throw new BitrixSignInException('unknown_portal');
        }

        // A valid token of another app on the portal is not a sign-in to ours.
        if (! $this->isOurAppToken($auth->accessToken)) {
            throw new BitrixSignInException('bitrix_rejected');
        }

        $user = $this->authenticate(new BitrixToken(
            accessToken: $auth->accessToken,
            refreshToken: $auth->refreshToken,
            expiresAt: CarbonImmutable::now()->addSeconds($auth->expiresIn ?: 3600),
            domain: $auth->domain,
            memberId: $auth->memberId,
        ));

        return $this->userRepository->createApiToken($user, self::TOKEN_NAME);
    }

    /**
     * The one-time code from the callback and this browser's nonce, for a Sanctum token.
     *
     * @throws BitrixLoginCodeExpiredException When the code is unknown, expired, already used, for another browser, or its user is inactive.
     */
    public function exchangeCode(ExchangeLoginCodeDTO $login): string
    {
        $user = $this->codes->redeem($login->code, $login->nonce);

        if (! $user instanceof User || ! $user->is_active) {
            throw new BitrixLoginCodeExpiredException;
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
            $profile = $this->bitrixApp->currentUser($token->accessToken);
        } catch (BitrixException) {
            throw new BitrixSignInException('bitrix_rejected');
        }

        if (empty($profile['ID'])) {
            throw new BitrixSignInException('bitrix_rejected');
        }

        // Never links by email here: anyone can put any email on their Bitrix profile.
        $user = $this->database->transaction(fn (): ?User => $this->syncUser->handle($profile));

        if (! $user instanceof User) {
            throw new BitrixSignInException('account_not_linked');
        }

        if (! $user->is_active) {
            throw new BitrixSignInException('inactive');
        }

        $this->tokens->store($user, $token);

        return $user;
    }

    /** For a local app, app.info's CODE is its client_id. */
    private function isOurAppToken(string $accessToken): bool
    {
        try {
            $code = BitrixPayload::of($this->bitrixApp->appInfo($accessToken), 'app.info')->stringOrNull('CODE') ?? '';
        } catch (BitrixException) {
            return false;
        }

        return hash_equals($this->configText('bitrix.client_id'), $code);
    }

    private function isOurPortal(mixed $domain): bool
    {
        return is_string($domain) && $domain !== '' && hash_equals($this->configText('bitrix.portal'), $domain);
    }

    /** A setting that may be unset: then it matches nothing. */
    private function configText(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }
}
