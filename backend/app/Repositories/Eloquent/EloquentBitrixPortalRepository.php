<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\BitrixPortal;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;

final class EloquentBitrixPortalRepository implements BitrixPortalRepositoryInterface
{
    public function current(): ?BitrixPortal
    {
        return BitrixPortal::current();
    }

    public function findByMemberId(string $memberId): ?BitrixPortal
    {
        return BitrixPortal::where('member_id', $memberId)->first();
    }

    public function saveInstall(string $memberId, array $attributes): BitrixPortal
    {
        return BitrixPortal::updateOrCreate(['member_id' => $memberId], $attributes);
    }

    public function update(BitrixPortal $portal, array $attributes): void
    {
        $portal->update($attributes);
    }
}
