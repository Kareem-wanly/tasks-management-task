<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TaskFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskFile */
final class TaskFileResource extends JsonResource
{
    /** @return array<string, mixed> */
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
