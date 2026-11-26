<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Task\TaskChecklistDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.3: { data: items, meta: { stale, can_edit } }.
 *
 * @property TaskChecklistDTO $resource
 */
class TaskChecklistResource extends JsonResource
{
    public static $wrap;

    public function toArray(Request $request): array
    {
        return [
            'data' => TaskChecklistItemResource::collection($this->resource->items),
            'meta' => [
                'stale' => $this->resource->stale,
                'can_edit' => $request->user()->can('updateChecklist', $this->resource->task),
            ],
        ];
    }
}
