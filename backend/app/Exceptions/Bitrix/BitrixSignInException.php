<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use App\Exceptions\BusinessException;

/**
 * Sign-in with Bitrix24 failed. The OAuth callback turns $reason into a
 * redirect to the SPA; the API (app session) answers it as JSON.
 */
final class BitrixSignInException extends BusinessException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Bitrix24 sign-in failed: {$reason}");
    }

    public function publicMessage(): string
    {
        return 'Sign-in with Bitrix24 was refused.';
    }

    public function extra(): array
    {
        return ['reason' => $this->reason];
    }
}
