<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Task\TaskChatDTO;
use App\Http\Resources\Concerns\ResolvesViewer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.7: { data: messages (oldest first), meta: { stale, can_post } }.
 *
 * @property TaskChatDTO $resource
 */
final class TaskChatResource extends JsonResource
{
    use ResolvesViewer;

    public static $wrap;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => TaskChatMessageResource::collection($this->resource->messages),
            'meta' => [
                'stale' => $this->resource->stale,
                'can_post' => $this->viewer($request)->can('postInChat', $this->resource->task),
            ],
        ];
    }
}
