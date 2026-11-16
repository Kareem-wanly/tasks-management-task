<?php

namespace App\Http\Requests;

use App\Models\TaskLookup;
use App\Models\TaskPriority;

class StoreTaskPriorityLookupRequest extends TaskLookupRequest
{
    protected function model(): string
    {
        return TaskPriority::class;
    }

    public function lookup(): ?TaskLookup
    {
        return null;
    }
}
