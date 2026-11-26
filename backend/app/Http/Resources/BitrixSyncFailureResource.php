<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BitrixSyncFailure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 5.2: one dead letter on the sync status page.
 *
 * @property BitrixSyncFailure $resource
 */
final class BitrixSyncFailureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'operation' => $this->resource->operation->value,
            'subject' => $this->resource->subject,
            'error' => $this->resource->error,
            'attempts' => $this->resource->attempts,
            'status' => $this->resource->status->value,
            'last_failed_at' => $this->resource->last_failed_at->toIso8601String(),
            'retried_at' => $this->resource->retried_at?->toIso8601String(),
            // Retry shows again once a queued retry goes stale (worker down).
            'can_retry' => $this->resource->canRetry(),
        ];
    }
}
