<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\BitrixSyncFailureStatus;
use App\Enums\BitrixSyncOperation;
use App\Models\BitrixSyncFailure;
use App\Repositories\Contracts\BitrixSyncFailureRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentBitrixSyncFailureRepository implements BitrixSyncFailureRepositoryInterface
{
    public function findOpen(BitrixSyncOperation $operation, string $subject): ?BitrixSyncFailure
    {
        return BitrixSyncFailure::where('operation', $operation->value)
            ->where('subject', $subject)
            ->whereIn('status', BitrixSyncFailureStatus::openValues())
            ->latest('id')
            ->first();
    }

    public function create(array $attributes): BitrixSyncFailure
    {
        return BitrixSyncFailure::create($attributes);
    }

    public function update(BitrixSyncFailure $failure, array $attributes): BitrixSyncFailure
    {
        $failure->fill($attributes)->save();

        return $failure;
    }

    public function open(int $limit): Collection
    {
        return BitrixSyncFailure::whereIn('status', BitrixSyncFailureStatus::openValues())
            ->orderByDesc('last_failed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function countOpen(): int
    {
        return BitrixSyncFailure::whereIn('status', BitrixSyncFailureStatus::openValues())->count();
    }
}
