<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The email or password is wrong.
 */
final class InvalidCredentialsException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Invalid login credentials');
    }

    public function status(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
