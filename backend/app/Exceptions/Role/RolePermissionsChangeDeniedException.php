<?php

declare(strict_types=1);

namespace App\Exceptions\Role;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

final class RolePermissionsChangeDeniedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Only an Administrator can modify permissions for this role.');
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
