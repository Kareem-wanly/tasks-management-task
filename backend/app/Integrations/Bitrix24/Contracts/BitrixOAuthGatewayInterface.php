<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Integrations\Bitrix24\BitrixToken;

/**
 * The Bitrix24 OAuth server (oauth.bitrix.info): the only way to get or renew
 * a token. Its implementation is the only class that talks to that server (ARC-15).
 */
interface BitrixOAuthGatewayInterface
{
    /** The code from the OAuth redirect, for a token pair. */
    public function exchangeCode(string $code): BitrixToken;

    /** A new token pair. Bitrix revokes the refresh token as soon as it is used. */
    public function refresh(string $refreshToken): BitrixToken;
}
