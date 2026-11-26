<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An activity log entry, with who did it.
 *
 * @property ActivityLog $resource
 */
final class ActivityLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'action' => $entry->action,
            'description' => $entry->description,
            'metadata' => $entry->metadata,
            'project_id' => $entry->project_id,
            'task_id' => $entry->task_id,
            'user' => new UserOptionResource($this->whenLoaded('user')),
            'created_at' => $entry->created_at?->toISOString(),
        ];
    }
}
