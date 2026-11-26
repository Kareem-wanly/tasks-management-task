<?php

declare(strict_types=1);

namespace App\Exceptions\Role;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

final class SystemRoleCannotBeDeletedException extends BusinessException
{
    public static function forRole(string $name): self
    {
        return new self("Protected system role [{$name}] cannot be deleted");
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
