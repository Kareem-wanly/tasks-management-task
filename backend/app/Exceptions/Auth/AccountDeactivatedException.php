<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account cannot sign in.
 */
final class AccountDeactivatedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('This account is deactivated');
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
