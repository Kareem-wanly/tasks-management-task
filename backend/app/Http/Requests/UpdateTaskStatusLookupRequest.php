<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\TaskLookup;
use App\Models\TaskStatus;

final class UpdateTaskStatusLookupRequest extends TaskLookupRequest
{
    public function lookup(): ?TaskLookup
    {
        return $this->route('taskStatus');
    }

    protected function model(): string
    {
        return TaskStatus::class;
    }

    protected function extraRules(): array
    {
        // Bitrix decides which of its statuses close a task.
        return $this->lookup()?->is_system ? [] : ['is_final' => ['boolean']];
    }
}
