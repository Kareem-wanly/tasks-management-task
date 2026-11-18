<?php

namespace App\Http\Resources;

use App\Data\TaskChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.3: { data: items, meta: { stale, can_edit } }.
 *
 * @property TaskChecklist $resource
 */
class TaskChecklistResource extends JsonResource
{
    public static $wrap = null;

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
