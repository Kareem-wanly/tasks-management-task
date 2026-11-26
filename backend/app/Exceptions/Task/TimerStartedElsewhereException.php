<?php

declare(strict_types=1);

namespace App\Exceptions\Task;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two starts raced (another tab); the other one won.
 */
final class TimerStartedElsewhereException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Your timer was just started from another tab. Refresh to see it.');
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
