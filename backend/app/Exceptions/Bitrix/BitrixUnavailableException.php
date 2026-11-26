<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use Symfony\Component\HttpFoundation\Response;

/**
 * Timeout or network failure: Bitrix never answered, so nothing was saved.
 */
final class BitrixUnavailableException extends BitrixException
{
    public function status(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }

    public function publicMessage(): string
    {
        return 'Bitrix24 is not responding. Your change was not saved; try again in a moment.';
    }
}
