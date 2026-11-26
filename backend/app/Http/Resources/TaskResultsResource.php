<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\TaskResults;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.6: { data: results, meta: { stale, can_add } }.
 *
 * @property TaskResults $resource
 */
class TaskResultsResource extends JsonResource
{
    public static $wrap;

    public function toArray(Request $request): array
    {
        return [
            'data' => TaskResultResource::collection($this->resource->results),
            'meta' => [
                'stale' => $this->resource->stale,
                'can_add' => $request->user()->can('addResult', $this->resource->task),
            ],
        ];
    }
}
