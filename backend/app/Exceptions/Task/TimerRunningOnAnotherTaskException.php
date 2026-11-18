<?php

declare(strict_types=1);

namespace App\Exceptions\Task;

use App\Exceptions\BusinessException;

/**
 * One running timer per person.
 */
final class TimerRunningOnAnotherTaskException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Your timer is running on another task. Stop it there.');
    }
}
