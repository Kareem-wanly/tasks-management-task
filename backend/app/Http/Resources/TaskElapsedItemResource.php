<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TaskElapsedItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name, 'avatar_url' => $this->user->avatar_url] : null,
            'seconds' => $this->seconds,
            'comment' => $this->comment,
            'created_date' => $this->created_date?->toISOString(),
            'can_edit' => $request->user()->can('manageTimeEntry', [$this->task, $this->resource]),
        ];
    }
}
