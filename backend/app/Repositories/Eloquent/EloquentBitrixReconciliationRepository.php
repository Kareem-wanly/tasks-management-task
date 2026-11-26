<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\BitrixReconciliation;
use App\Repositories\Contracts\BitrixReconciliationRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class EloquentBitrixReconciliationRepository implements BitrixReconciliationRepositoryInterface
{
    public function create(array $attributes): BitrixReconciliation
    {
        return BitrixReconciliation::create($attributes);
    }

    public function since(string $entity, CarbonImmutable $since): Collection
    {
        return BitrixReconciliation::where('entity', $entity)
            ->where('checked_at', '>=', $since)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get();
    }

    public function pruneBefore(CarbonImmutable $before): void
    {
        BitrixReconciliation::where('checked_at', '<', $before)->delete();
    }
}
