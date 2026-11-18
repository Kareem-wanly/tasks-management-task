<?php

declare(strict_types=1);

namespace App\Exceptions\User;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nobody assigns a role whose permissions they do not have themselves.
 */
final class RoleAssignmentDeniedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('You cannot assign roles with permissions that you do not possess.');
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
