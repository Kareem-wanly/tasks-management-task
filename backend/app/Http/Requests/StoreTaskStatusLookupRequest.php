<?php

namespace App\Http\Requests;

use App\Models\TaskLookup;
use App\Models\TaskStatus;

class StoreTaskStatusLookupRequest extends TaskLookupRequest
{
    protected function model(): string
    {
        return TaskStatus::class;
    }

    public function lookup(): ?TaskLookup
    {
        return null;
    }

    protected function extraRules(): array
    {
        return ['is_final' => ['boolean']];
    }
}
