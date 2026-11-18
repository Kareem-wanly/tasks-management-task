<?php

declare(strict_types=1);

namespace App\Models;

class TaskPriority extends TaskLookup
{
    public static function taskColumn(): string
    {
        return 'priority';
    }
}
