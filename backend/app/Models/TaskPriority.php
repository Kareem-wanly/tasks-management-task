<?php

namespace App\Models;

class TaskPriority extends TaskLookup
{
    public static function taskColumn(): string
    {
        return 'priority';
    }
}
