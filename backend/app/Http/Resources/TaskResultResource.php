<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TaskResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'status' => $this->status,
            'author' => $this->author ? ['id' => $this->author->id, 'name' => $this->author->name, 'avatar_url' => $this->author->avatar_url] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can_edit' => $request->user()->can('manageResult', [$this->task, $this->resource]),
        ];
    }
}
