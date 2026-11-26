<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date?->toISOString(),
            'is_overdue' => $this->is_overdue,
            'completed_at' => $this->completed_at?->toISOString(),
            'allow_time_tracking' => (bool) $this->allow_time_tracking,
            'time_estimate' => (int) $this->time_estimate,
            'time_spent' => (int) $this->time_spent,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'accomplices' => UserResource::collection($this->whenLoaded('accomplices')),
            'auditors' => UserResource::collection($this->whenLoaded('auditors')),
            'parent' => $this->whenLoaded('parent', fn (): ?array => $this->parent ? ['id' => $this->parent->id, 'title' => $this->parent->title] : null),
            'subtasks' => self::collection($this->whenLoaded('subtasks')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
