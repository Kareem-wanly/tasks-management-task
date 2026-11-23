<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixOAuthGatewayInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * The only class that talks to the Bitrix24 OAuth server. The query string
 * carries client_secret, so a failure is reported through BitrixException,
 * which redacts it (SEC-5).
 */
final readonly class BitrixOAuthGateway implements BitrixOAuthGatewayInterface
{
    public function exchangeCode(string $code): BitrixToken
    {
        return $this->requestToken(['grant_type' => 'authorization_code', 'code' => $code]);
    }

    public function refresh(string $refreshToken): BitrixToken
    {
        return $this->requestToken(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    /** @param  array<string, mixed>  $params */
    private function requestToken(array $params): BitrixToken
    {
        try {
            $response = Http::acceptJson()
                ->timeout(Config::integer('bitrix.timeout'))
                ->get(rtrim(Config::string('bitrix.oauth_server'), '/').'/oauth/token/', [
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
