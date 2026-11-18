<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'is_system' => $this->is_system,
            'author' => $this->author ? ['id' => $this->author->id, 'name' => $this->author->name, 'avatar_url' => $this->author->avatar_url] : null,
            'files' => $this->files ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can_edit' => $this->resource->isChangeableBy($request->user()) && $request->user()->can('manageChatMessage', [$this->task, $this->resource]),
        ];
    }
}
