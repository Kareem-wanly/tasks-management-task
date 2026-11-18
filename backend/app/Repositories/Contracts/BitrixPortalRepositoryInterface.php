<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\BitrixPortal;

interface BitrixPortalRepositoryInterface
{
    /** Our installed portal, if any. */
    public function current(): ?BitrixPortal;

    public function findByMemberId(string $memberId): ?BitrixPortal;

    /** @param  array<string, mixed>  $attributes */
    public function saveInstall(string $memberId, array $attributes): BitrixPortal;

    /** @param  array<string, mixed>  $attributes */
    public function update(BitrixPortal $portal, array $attributes): void;
}
