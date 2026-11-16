<?php

namespace App\Http\Requests;

use App\Models\TaskLookup;
use App\Models\TaskStatus;

class UpdateTaskStatusLookupRequest extends TaskLookupRequest
{
    protected function model(): string
    {
        return TaskStatus::class;
    }

    public function lookup(): ?TaskLookup
    {
        return $this->route('taskStatus');
    }

    protected function extraRules(): array
    {
        // Bitrix decides which of its statuses close a task.
        return $this->lookup()?->is_system ? [] : ['is_final' => ['boolean']];
    }
}
