<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesViewer;
use App\Models\TaskChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TaskChatMessageResource extends JsonResource
{
    use ResolvesViewer;

    /** @return array<string, mixed> */
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
            'can_edit' => $this->resource instanceof TaskChatMessage && $this->resource->isChangeableBy($this->viewer($request)) && $this->viewer($request)->can('manageChatMessage', [$this->task, $this->resource]),
        ];
    }
}
