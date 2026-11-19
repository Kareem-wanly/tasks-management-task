<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Bitrix\BitrixFrameAuthDTO;
use App\DTOs\Bitrix\ExchangeLoginCodeDTO;
use App\Exceptions\Bitrix\BitrixLoginCodeExpiredException;
use App\Models\User;

interface BitrixAuthServiceInterface
{
    /** Bitrix's consent page; $state comes back with the code. */
    public function authorizeUrl(string $state): string;

    /** OAuth: the code Bitrix sent back, for our portal. Returns a one-time sign-in code bound to $nonce. */
    public function signInWithCode(string $code, mixed $domain, string $nonce): string;

    /**
     * The app opened inside Bitrix: the tokens Bitrix's JS SDK gave the app
     * page (the Bitrix user signed in around it). Returns a Sanctum token.
     */
    public function signInFromFrame(BitrixFrameAuthDTO $auth): string;

    /**
     * The one-time code from the callback and this browser's nonce, for a Sanctum token.
     *
     * @throws BitrixLoginCodeExpiredException When the code is unknown, expired, already used, for another browser, or its user is inactive.
     */
    public function exchangeCode(ExchangeLoginCodeDTO $login): string;
}
