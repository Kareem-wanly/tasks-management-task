<?php

declare(strict_types=1);

namespace App\Exceptions\Task;

use App\Exceptions\BusinessException;

/**
 * Only checklist items move; a list keeps its place.
 */
final class ChecklistListNotMovableException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Lists cannot be moved.');
    }
}
