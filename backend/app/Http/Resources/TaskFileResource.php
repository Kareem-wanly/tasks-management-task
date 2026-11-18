<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'size' => $this->size,
            'mime' => $this->mime,
            'uploader' => $this->uploader ? ['id' => $this->uploader->id, 'name' => $this->uploader->name] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
