<?php

declare(strict_types=1);

namespace App\Exceptions\Role;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

final class ReservedRoleNameException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Cannot rename a role to a reserved system role name.');
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
