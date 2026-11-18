<?php

namespace App\Policies;

use App\Models\TaskLookup;
use App\Models\User;

/** Task statuses and priorities: everyone reads them, task_settings.manage changes them. */
class TaskLookupPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('task_settings.manage');
    }

    public function update(User $user, TaskLookup $lookup): bool
    {
        return $user->hasPermission('task_settings.manage');
    }

    public function delete(User $user, TaskLookup $lookup): bool
    {
        return $user->hasPermission('task_settings.manage');
    }
}
