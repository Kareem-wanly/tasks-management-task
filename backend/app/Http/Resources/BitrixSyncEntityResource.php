<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Bitrix\BitrixSyncEntityDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property BitrixSyncEntityDTO $resource
 */
final class BitrixSyncEntityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'entity' => $this->resource->entity,
            'state' => $this->resource->state->value,
            'processed' => $this->resource->processed,
            'total' => $this->resource->total,
            'error' => $this->resource->error,
            'started_at' => $this->resource->startedAt?->toIso8601String(),
            'last_synced_at' => $this->resource->lastSyncedAt?->toIso8601String(),
        ];
    }
}
