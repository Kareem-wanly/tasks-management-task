<?php

declare(strict_types=1);

namespace App\Exceptions\User;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

final class AdministratorRoleChangeDeniedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Only an Administrator can assign or revoke the Administrator role.');
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
