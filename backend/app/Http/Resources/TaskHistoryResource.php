<?php

namespace App\Http\Resources;

use App\Data\TaskHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.8: { data: entries (newest first), meta: { unavailable_because } }.
 *
 * @property TaskHistory $resource
 */
class TaskHistoryResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->resource->entries,
            'meta' => ['unavailable_because' => $this->resource->unavailableBecause],
        ];
    }
}
