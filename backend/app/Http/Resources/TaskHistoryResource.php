<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Task\TaskHistoryDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.8: { data: entries (newest first), meta: { unavailable_because } }.
 *
 * @property TaskHistoryDTO $resource
 */
final class TaskHistoryResource extends JsonResource
{
    public static $wrap;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->resource->entries,
            'meta' => ['unavailable_because' => $this->resource->unavailableBecause],
        ];
    }
}
