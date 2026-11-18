<?php

declare(strict_types=1);

namespace App\Exceptions\Role;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

/**
 * System roles keep their names, so the app can rely on them.
 */
final class SystemRoleCannotBeRenamedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('System roles cannot be renamed.');
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
