<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use Symfony\Component\HttpFoundation\Response;

/**
 * Bitrix24 answered, but not in the shape its API documents (a missing field,
 * a number that isn't one). The message names the method and the field, never
 * the value, which could carry a token (SEC-5).
 */
final class BitrixUnexpectedResponseException extends BitrixException
{
    public function status(): int
    {
        return Response::HTTP_BAD_GATEWAY;
    }

    public function publicMessage(): string
    {
        return 'Bitrix24 sent an answer we could not read. Try again in a moment; if it keeps happening, tell an administrator.';
    }
}
