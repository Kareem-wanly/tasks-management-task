<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\TaskLookup;
use App\Models\TaskPriority;

class UpdateTaskPriorityLookupRequest extends TaskLookupRequest
{
    public function lookup(): ?TaskLookup
    {
        return $this->route('taskPriority');
    }

    protected function model(): string
    {
        return TaskPriority::class;
    }
}
