<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use Symfony\Component\HttpFoundation\Response;

/**
 * Bitrix answered with an error ($errorCode is Bitrix's code).
 */
final class BitrixRequestFailedException extends BitrixException
{
    public function status(): int
    {
        return $this->isAccessDenied() ? Response::HTTP_FORBIDDEN : Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    public function publicMessage(): string
    {
        return $this->isAccessDenied()
            ? 'Bitrix24 does not allow you to do this.'
            : 'Bitrix24 rejected the change: '.$this->getMessage();
    }
}
