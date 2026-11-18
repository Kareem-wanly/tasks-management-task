<?php

namespace App\Integrations\Bitrix24\Contracts;

use App\Integrations\Bitrix24\BitrixToken;

/**
 * Anything that owns a Bitrix24 token: a user (step 1.5) or the portal's
 * service account (step 1.4).
 */
interface HasBitrixToken
{
    public function bitrixToken(): ?BitrixToken;

    public function storeBitrixToken(BitrixToken $token): void;

    /**
     * Unique per token owner, so only one process refreshes it at a time.
     */
    public function bitrixTokenLockKey(): string;
}
