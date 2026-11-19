<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\TaskLookup;
use App\Models\TaskStatus;

final class StoreTaskStatusLookupRequest extends TaskLookupRequest
{
    public function lookup(): ?TaskLookup
    {
        return null;
    }

    protected function model(): string
    {
        return TaskStatus::class;
    }

    protected function extraRules(): array
    {
        return ['is_final' => ['boolean']];
    }
}
