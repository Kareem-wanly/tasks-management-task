<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step 5.1: a sync is running, or is queued and not started yet; a second
 * one would only repeat the same work.
 */
final class BitrixSyncAlreadyRunningException extends BusinessException
{
    public static function running(): self
    {
        return new self('A Bitrix24 sync is already running. Wait for it to finish.');
    }

    /** Usually brief; if it lasts, the background worker is not running. */
    public static function queued(): self
    {
        return new self('A Bitrix24 sync is already queued. It starts when the background worker picks it up.');
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
