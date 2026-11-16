<?php

namespace App\Http\Requests;

use App\Models\TaskLookup;
use App\Models\TaskPriority;

class UpdateTaskPriorityLookupRequest extends TaskLookupRequest
{
    protected function model(): string
    {
        return TaskPriority::class;
    }

    public function lookup(): ?TaskLookup
    {
        return $this->route('taskPriority');
    }
}
