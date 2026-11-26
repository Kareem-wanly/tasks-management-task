<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use App\Exceptions\BusinessException;

/**
 * The one-time sign-in code is used once, within its lifetime, by the browser that asked for it.
 */
final class BitrixLoginCodeExpiredException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('The sign-in link expired or was already used. Please sign in again.');
    }
}
