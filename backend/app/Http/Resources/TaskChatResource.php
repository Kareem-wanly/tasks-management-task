<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\TaskChat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.7: { data: messages (oldest first), meta: { stale, can_post } }.
 *
 * @property TaskChat $resource
 */
class TaskChatResource extends JsonResource
{
    public static $wrap;

    public function toArray(Request $request): array
    {
        return [
            'data' => TaskChatMessageResource::collection($this->resource->messages),
            'meta' => [
                'stale' => $this->resource->stale,
                'can_post' => $request->user()->can('postInChat', $this->resource->task),
            ],
        ];
    }
}
