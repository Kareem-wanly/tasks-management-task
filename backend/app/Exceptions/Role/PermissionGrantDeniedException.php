<?php

declare(strict_types=1);

namespace App\Exceptions\Role;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nobody grants a permission they do not have themselves.
 */
final class PermissionGrantDeniedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('You cannot grant permissions that you do not possess.');
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
