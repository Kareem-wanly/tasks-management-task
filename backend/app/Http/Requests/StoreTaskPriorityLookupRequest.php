<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\TaskLookup;
use App\Models\TaskPriority;

class StoreTaskPriorityLookupRequest extends TaskLookupRequest
{
    public function lookup(): ?TaskLookup
    {
        return null;
    }

    protected function model(): string
    {
        return TaskPriority::class;
    }
}
