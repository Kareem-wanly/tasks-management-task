<?php

declare(strict_types=1);

namespace App\Exceptions\Project;

use App\Exceptions\BusinessException;

/**
 * A project is completed only when its tasks are.
 */
final class ProjectHasUnfinishedTasksException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Cannot complete project with unfinished tasks.');
    }

    public function errors(): array
    {
        return ['status' => ['Project has pending or in-progress tasks that must be completed first.']];
    }
}
