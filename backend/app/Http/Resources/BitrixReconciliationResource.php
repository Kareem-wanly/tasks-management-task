<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Bitrix\BitrixReconciliationSummaryDTO;
use App\Models\BitrixReconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 5.3: the nightly reconciliation of one entity. `latest` is null until
 * its first full import.
 *
 * @property BitrixReconciliationSummaryDTO $resource
 */
final class BitrixReconciliationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $latest = $this->resource->latest;

        return [
            'entity' => $this->resource->entity,
            'clean_nights' => $this->resource->cleanNights,
            'latest' => $latest instanceof BitrixReconciliation ? [
                'checked_at' => $latest->checked_at->toIso8601String(),
                'bitrix_count' => $latest->bitrix_count,
                'local_count' => $latest->local_count,
                'missing_here_count' => $latest->missing_here_count,
                'extra_here_count' => $latest->extra_here_count,
                'missing_here' => $latest->missing_here,
                'extra_here' => $latest->extra_here,
            ] : null,
            // The last days, oldest first, ending today.
            'nights' => array_map(fn (array $night): array => ['date' => $night['date'], 'state' => $night['state']->value], $this->resource->nights),
        ];
    }
}
