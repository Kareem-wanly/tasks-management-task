<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Task\TaskResultsDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.6: { data: results, meta: { stale, can_add } }.
 *
 * @property TaskResultsDTO $resource
 */
final class TaskResultsResource extends JsonResource
{
    public static $wrap;

    /** @return array<string, mixed> */
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
