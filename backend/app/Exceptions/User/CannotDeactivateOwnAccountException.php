<?php

declare(strict_types=1);

namespace App\Exceptions\User;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

final class CannotDeactivateOwnAccountException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('You cannot deactivate your own account.');
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
