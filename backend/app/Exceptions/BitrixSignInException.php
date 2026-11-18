<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * "Sign in with Bitrix24" failed. The reason code goes back to the frontend
 * in the callback URL (#error=reason).
 */
class BitrixSignInException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Bitrix24 sign-in failed: {$reason}");
    }
}
