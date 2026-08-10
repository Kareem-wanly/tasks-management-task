<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => $this->status,
            'priority'    => $this->priority ?? 'medium',
            'due_date'    => $this->due_date?->toISOString(),
            'project_id'  => $this->project_id,
            'project'     => new ProjectResource($this->whenLoaded('project')),
            'assignee'    => new UserResource($this->whenLoaded('assignee')),
            'creator'     => new UserResource($this->whenLoaded('creator')),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}