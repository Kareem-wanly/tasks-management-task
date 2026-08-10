<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title ?? $this->name,
            'description' => $this->description,
            'status'      => $this->status ?? 'active',
            'owner_id'    => $this->owner_id,
            'owner'       => new UserResource($this->whenLoaded('owner')),
            'members'     => UserResource::collection($this->whenLoaded('members')),
            'tasks_count' => $this->whenCounted('tasks'),
            'start_date'  => $this->start_date?->toISOString(),
            'due_date'    => $this->due_date?->toISOString(),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}