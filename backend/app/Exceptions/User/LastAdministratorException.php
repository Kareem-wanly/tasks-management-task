<?php

declare(strict_types=1);

namespace App\Exceptions\User;

use App\Exceptions\BusinessException;

/**
 * The portal always keeps at least one active administrator.
 */
final class LastAdministratorException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Cannot revoke Administrator role from the final active administrator.');
    }
}
