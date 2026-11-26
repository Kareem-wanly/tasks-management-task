<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\BitrixSyncOperation;
use App\Models\BitrixSyncFailure;
use Illuminate\Support\Collection;

/**
 * Step 5.2: background Bitrix24 work that failed after all its tries.
 */
interface BitrixSyncFailureRepositoryInterface
{
    /** The open (pending or retrying) row of this operation, if any. */
    public function findOpen(BitrixSyncOperation $operation, string $subject): ?BitrixSyncFailure;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): BitrixSyncFailure;

    /** @param  array<string, mixed>  $attributes */
    public function update(BitrixSyncFailure $failure, array $attributes): BitrixSyncFailure;

    /**
     * The open rows, most recent failure first.
     *
     * @return Collection<int, BitrixSyncFailure>
     */
    public function open(int $limit): Collection;

    public function countOpen(): int;
}
