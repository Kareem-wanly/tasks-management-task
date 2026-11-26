<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Bitrix\BitrixSyncStatusDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 5.1: { data: { is_running, entities, failed_jobs, conflicts, unconfirmed } }.
 *
 * @property BitrixSyncStatusDTO $resource
 */
final class BitrixSyncStatusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'is_running' => $this->resource->isRunning(),
            'entities' => BitrixSyncEntityResource::collection($this->resource->entities),
            'failed_jobs' => $this->resource->failedJobs,
            'conflicts' => $this->resource->conflicts,
            'unconfirmed' => $this->resource->unconfirmed,
        ];
    }
}
