<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Integrations\Bitrix24\BitrixToken;

/** Keeps a stored Bitrix24 token usable: when to renew it, and only once at a time. */
interface TokenManagerInterface
{
    /**
     * A usable access token, renewed a minute before it expires, or at once
     * with $force after Bitrix answered expired_token.
     */
    public function validTokenFor(HasBitrixToken $holder, bool $force = false): BitrixToken;
}
