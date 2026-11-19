<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class TokenManager
{
    private const REFRESH_MARGIN_SECONDS = 60;

    private const LOCK_SECONDS = 15;

    public function exchangeCode(string $code): BitrixToken
    {
        return $this->requestToken(['grant_type' => 'authorization_code', 'code' => $code]);
    }

    public function refresh(BitrixToken $token): BitrixToken
    {
        return $this->requestToken(['grant_type' => 'refresh_token', 'refresh_token' => $token->refreshToken]);
    }

    /**
     * Return a usable access token, refreshing it a minute before expiry
     * (or immediately when $force is set after an expired_token reply).
     *
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
        return Cache::lock($holder->bitrixTokenLockKey(), self::LOCK_SECONDS)
            ->block(self::LOCK_SECONDS, function () use ($holder, $token, $force): BitrixToken {
                if ($holder instanceof Model && $holder->exists) {
                    $holder->refresh();
                }

                $current = $holder->bitrixToken() ?? throw BitrixException::missingToken();

                $refreshedElsewhere = $current->refreshToken !== $token->refreshToken;
                if ($refreshedElsewhere || (! $force && ! $current->expiresWithin(self::REFRESH_MARGIN_SECONDS))) {
                    return $current;
                }

                $fresh = $this->refresh($current);
                $holder->storeBitrixToken($fresh);

                return $fresh;
            });
    }

    private function requestToken(array $params): BitrixToken
    {
        try {
            $response = Http::acceptJson()
                ->timeout(config('bitrix.timeout'))
                ->get(rtrim(config('bitrix.oauth_server'), '/').'/oauth/token/', [
                    'client_id' => config('bitrix.client_id'),
                    'client_secret' => config('bitrix.client_secret'),
                    ...$params,
                ]);
        } catch (ConnectionException $e) {
            throw BitrixException::unreachable($e->getMessage());
        }

        $body = $response->json();

        if ($response->failed() || ! is_array($body) || isset($body['error']) || ! isset($body['access_token'])) {
            throw BitrixException::fromResponse(is_array($body) ? $body : null, $response->status());
        }

        return BitrixToken::fromOAuthResponse($body);
    }
}
