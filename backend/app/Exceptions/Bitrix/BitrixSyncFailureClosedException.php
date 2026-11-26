<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step 5.2: the failure can't be retried or dismissed (someone else already
 * did, or the operation succeeded meanwhile).
 */
final class BitrixSyncFailureClosedException extends BusinessException
{
    public static function closed(): self
    {
        return new self('This failure is already closed. Refresh the page.');
    }

    public static function retryQueued(): self
    {
        return new self('A retry of this failure is already queued. It starts when the background worker picks it up.');
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
