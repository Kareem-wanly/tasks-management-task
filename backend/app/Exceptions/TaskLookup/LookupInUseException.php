<?php

declare(strict_types=1);

namespace App\Exceptions\TaskLookup;

use App\Exceptions\BusinessException;

final class LookupInUseException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('This value is used by tasks and cannot be deleted.');
    }
}
