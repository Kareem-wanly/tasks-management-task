<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixOAuthGatewayInterface;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Integrations\Bitrix24\Contracts\TokenManagerInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** When to renew a stored token, and only once at a time; the OAuth gateway renews it. */
final readonly class TokenManager implements TokenManagerInterface
{
    private const REFRESH_MARGIN_SECONDS = 60;

    private const LOCK_SECONDS = 15;

    public function __construct(private BitrixOAuthGatewayInterface $oauth) {}

    /**
     * Bitrix revokes a refresh token as soon as it is used, so the refresh
     * runs under a lock and re-reads the stored token first: if another
     * process already refreshed it, that new token is reused.
     */
    public function validTokenFor(HasBitrixToken $holder, bool $force = false): BitrixToken
    {
        $token = $holder->bitrixToken() ?? throw BitrixException::missingToken();

        if (! $force && ! $token->expiresWithin(self::REFRESH_MARGIN_SECONDS)) {
            return $token;
        }

        try {
            return $this->refreshUnderLock($holder, $token, $force);
        } catch (LockTimeoutException) {
            // Another process kept refreshing for too long: Bitrix is most likely slow.
            throw BitrixException::unreachable('Timed out waiting for the Bitrix24 token refresh.');
        }
    }

    private function refreshUnderLock(HasBitrixToken $holder, BitrixToken $token, bool $force): BitrixToken
    {
        $lock = Cache::lock($holder->bitrixTokenLockKey(), self::LOCK_SECONDS);
        $lock->block(self::LOCK_SECONDS);

        try {
            if ($holder instanceof Model && $holder->exists) {
                $holder->refresh();
            }

            $current = $holder->bitrixToken() ?? throw BitrixException::missingToken();

            $refreshedElsewhere = $current->refreshToken !== $token->refreshToken;
            if ($refreshedElsewhere || (! $force && ! $current->expiresWithin(self::REFRESH_MARGIN_SECONDS))) {
                return $current;
            }

            $fresh = $this->oauth->refresh($current->refreshToken);
            $holder->storeBitrixToken($fresh);

            return $fresh;
        } finally {
            $lock->release();
        }
    }
}
