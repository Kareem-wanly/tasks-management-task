<?php

namespace App\Repositories\Eloquent;

use App\Models\BitrixPortal;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;

class BitrixPortalRepository implements BitrixPortalRepositoryInterface
{
    public function current(): ?BitrixPortal
    {
        return BitrixPortal::current();
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
