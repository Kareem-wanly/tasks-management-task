<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Task\TaskChecklistDTO;
use App\Http\Resources\Concerns\ResolvesViewer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.3: { data: items, meta: { stale, can_edit } }.
 *
 * @property TaskChecklistDTO $resource
 */
final class TaskChecklistResource extends JsonResource
{
    use ResolvesViewer;

    public static $wrap;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => TaskChecklistItemResource::collection($this->resource->items),
            'meta' => [
                'stale' => $this->resource->stale,
                'can_edit' => $this->viewer($request)->can('updateChecklist', $this->resource->task),
            ],
        ];
    }
}
