<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\BitrixReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Step 5.3: the checks of the nightly reconciliation.
 */
interface BitrixReconciliationRepositoryInterface
{
    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): BitrixReconciliation;

    /**
     * The entity's checks made since $since, newest first.
     *
     * @return Collection<int, BitrixReconciliation>
     */
    public function since(string $entity, CarbonImmutable $since): Collection;

    /** Deletes the checks made before $before. */
    public function pruneBefore(CarbonImmutable $before): void;
}
