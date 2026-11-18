<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use Symfony\Component\HttpFoundation\Response;

/**
 * No Bitrix24 token for this account (or the portal): the employee must sign
 * in with Bitrix24 before changing synced data.
 */
final class BitrixTokenMissingException extends BitrixException
{
    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function publicMessage(): string
    {
        return 'Sign in with Bitrix24 to change data that is synced with Bitrix24.';
    }
}
